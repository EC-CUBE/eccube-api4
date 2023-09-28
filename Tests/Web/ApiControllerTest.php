<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Api42\Tests\Web;

use Eccube\Tests\Web\AbstractWebTestCase;
use League\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use League\Bundle\OAuth2ServerBundle\Entity\RefreshToken;
use League\Bundle\OAuth2ServerBundle\Entity\Scope;
use League\Bundle\OAuth2ServerBundle\Manager\Doctrine\ClientManager;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class ApiControllerTest extends AbstractWebTestCase
{
    /** @var ClientManager */
    private $clientManager;

    /** @var ClientRepositoryInterface */
    private $clientRepository;

    /** @var AccessTokenRepositoryInterface */
    private $accessTokenRepository;

    /** @var AuthorizationServer */
    private $authorizationServer;

    private $refreshTokenRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->clientManager = self::$container->get(ClientManager::class);
        $this->clientRepository = self::$container->get(ClientRepositoryInterface::class);
        $this->accessTokenRepository = self::$container->get(AccessTokenRepositoryInterface::class);
        $this->refreshTokenRepository = self::$container->get(RefreshTokenRepositoryInterface::class);
        $this->authorizationServer = self::$container->get(AuthorizationServer::class);
    }

    /**
     * @dataProvider permissionProvider
     */
    public function testPermission($scopes, $query, $expectedErrorMessage = null)
    {
        $this->deleteAllRows(['dtb_customer']);

        $headers = [];
        if ($scopes) {
            $token = $this->newAccessToken($scopes);
            $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        }
        $this->client->request('POST', $this->generateUrl('api'), [], [], $headers, json_encode(['query' => $query]));

        self::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true);

        if ($expectedErrorMessage) {
            self::assertEquals($expectedErrorMessage, $payload['errors'][0]['message']);
        } else {
            self::assertFalse(isset($payload['errors']), json_encode(@$payload['errors']));
        }
    }

    public function permissionProvider()
    {
        return [
            [['read:Product'], '{ product(id:1) { id, name } }'],
            [['read:Product'], '{ customer(id:1) { id } }', 'Cannot query field "customer" on type "Query".'],
            [['read:Customer'], '{ customer(id:1) { id } }', 'Eccube\\Entity\\Customer not found'],
            [['read:Product'], '{ product(id:1) { id, name, ProductClasses { id } } }', 'Cannot query field "ProductClasses" on type "Product".'],
            [['read:Product', 'read:ProductClass'], '{ product(id:1) { id, name, ProductClasses { id } } }'],
            [['read:Product', 'read:Member'], '{ product(id:1) { id, name, Creator { id } } }'],
            [['read:Customer'], '{ customer(id:1) { id, password } }', 'Cannot query field "password" on type "Customer".'],
            [null, '{ product(id:1) { id, name } }'],
            [null, '{ product(id:1) { id, name, Creator { id } } }', 'Cannot query field "Creator" on type "Product".'],
            [['read:ProductClass'], 'mutation { updateProductStock(code: "sand-01", stock: 10, stock_unlimited:false) { id } }', 'Cannot write entity. `Eccube\\Entity\\ProductClass`'],
            [['read:ProductClass', 'write:ProductClass'], 'mutation { updateProductStock(code: "sand-01", stock: 10, stock_unlimited:false) { id } }', 'Cannot write entity. `Eccube\\Entity\\ProductStock`'],
            [['read:ProductClass', 'write:ProductClass', 'write:ProductStock'], 'mutation { updateProductStock(code: "sand-01", stock: 10, stock_unlimited:false) { id } }'],
        ];
    }

    /**
     * @dataProvider logAndInvalidate0Auth2SessionProvider
     */
    public function testLogoutAndInvalidateOAuth2Session(string $identifier, array $post_login_data, array $post_logout_data)
    {
        $this->deleteAllRows(['oauth2_refresh_token', 'oauth2_access_token']);

        $customer = $this->createCustomer();

        $token = $this->newAccessToken(
            scopes: ['read:Cart', 'write:Cart', 'read:CartItem', 'write:CartItem', 'read:Category', 'read:ClassCategory', 'read:ClassName', 'read:Country', 'read:Customer', 'write:Customer', 'read:CustomerAddress', 'read:CustomerFavoriteProduct', 'read:CustomerOrderStatus', 'read:CustomerStatus', 'read:Delivery', 'read:News', 'read:Order', 'read:OrderItem', 'read:OrderItemType', 'read:OrderPdf', 'read:OrderStatus', 'read:Pref', 'read:Product', 'read:ProductCategory', 'read:ProductClass', 'read:ProductImage', 'read:ProductListMax', 'read:ProductListOrderBy', 'read:ProductStatus', 'read:ProductStock', 'read:ProductTag', 'read:Shipping'],
            name: $customer->getEmail(),
            identifier: $post_login_data['oauth2_access_token']['identifier'],
            secret: $post_login_data['oauth2_access_token']['identifier'],
            expiryDateTime: $post_login_data['oauth2_access_token']['expiry'],
            requireRefreshToken: true,
            type: 'password',
        );

        // アクセストークンが発行されていることを確認する
        $tokenList = $this->entityManager->getRepository(\League\Bundle\OAuth2ServerBundle\Model\AccessToken::class)->findBy(['userIdentifier' => $customer->getEmail()]);
        self::assertCount(1, $tokenList);
        self::assertEquals($post_login_data['oauth2_access_token']['identifier'], trim($tokenList[0]->getIdentifier()));
        self::assertEquals($post_login_data['oauth2_access_token']['expiry']->format('Y-m-d H:i:s'), $tokenList[0]->getExpiry()->format('Y-m-d H:i:s'));
        self::assertEquals($post_login_data['oauth2_access_token']['revoked'], $tokenList[0]->isRevoked());

        // リフレッシュトークンが発行されていることを確認する
        $refreshList = $this->entityManager->getRepository(\League\Bundle\OAuth2ServerBundle\Model\RefreshToken::class)->findBy(['accessToken' => $post_login_data['oauth2_access_token']['identifier']]);
        self::assertCount(1, $refreshList);
        self::assertEquals($post_login_data['oauth2_refresh_token']['access_token'], trim($refreshList[0]->getAccessToken()));
        self::assertEquals($post_login_data['oauth2_refresh_token']['expiry']->format('Y-m-d H:i:s'), $refreshList[0]->getExpiry()->format('Y-m-d H:i:s'));
        self::assertEquals($post_login_data['oauth2_refresh_token']['revoked'], $refreshList[0]->isRevoked());

        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        $this->client->request('POST', $this->generateUrl('api_logout'), [], [], $headers);

        if ($post_logout_data['oauth2_access_token']['deleted'] === true) {
            // トークンが削除されている場合は、トークンが無効であることを確認する
            self::assertEquals(401, $this->client->getResponse()->getStatusCode());
            return;
        }

        // トークンが削除されていない場合は、トークンが無効化されていることを確認する
        self::assertEquals(200, $this->client->getResponse()->getStatusCode());
        self::assertEquals('application/json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertEquals('{"message":"success"}', $this->client->getResponse()->getContent());

        $tokenList = $this->entityManager->getRepository(\League\Bundle\OAuth2ServerBundle\Model\AccessToken::class)->findBy(['userIdentifier' => $customer->getEmail()]);
        self::assertCount(1, $tokenList);
        self::assertEquals($post_logout_data['oauth2_access_token']['identifier'], trim($tokenList[0]->getIdentifier()));
        self::assertEquals($post_logout_data['oauth2_access_token']['expiry']->format('Y-m-d H:i:s'), $tokenList[0]->getExpiry()->format('Y-m-d H:i:s'));
        self::assertEquals($post_logout_data['oauth2_access_token']['revoked'], $tokenList[0]->isRevoked());

        $refreshList = $this->entityManager->getRepository(\League\Bundle\OAuth2ServerBundle\Model\RefreshToken::class)->findBy(['accessToken' => $post_login_data['oauth2_access_token']['identifier']]);
        self::assertCount(1, $refreshList);
        self::assertEquals($post_logout_data['oauth2_refresh_token']['access_token'], trim($refreshList[0]->getAccessToken()));
        self::assertEquals($post_logout_data['oauth2_refresh_token']['expiry']->format('Y-m-d H:i:s'), $refreshList[0]->getExpiry()->format('Y-m-d H:i:s'));
        self::assertEquals($post_logout_data['oauth2_refresh_token']['revoked'], $refreshList[0]->isRevoked());
    }

    public function logAndInvalidate0Auth2SessionProvider(): array
    {
        $clientInfo = [
            'test_active_user@example.com' => [
                'identifier' => hash('md5', random_bytes(16)),
                'expiry' => new \DateTimeImmutable('+1 days', new \DateTimeZone('Asia/Tokyo')),
            ],
            'inactive_user@example.com' => [
                'identifier' => hash('md5', random_bytes(16)),
                'secret' => hash('sha512', random_bytes(32)),
                'expiry' => new \DateTimeImmutable('-1 days', new \DateTimeZone('Asia/Tokyo')),
            ],
        ];

        return [
            [
                'identifier' => 'test_active_user@example.com',
                'post_login_data' => [
                    'oauth2_access_token' => [
                        'user_identifier' => 'test_active_user@example.com',
                        'identifier' => $clientInfo['test_active_user@example.com']['identifier'],
                        'expiry' => $clientInfo['test_active_user@example.com']['expiry'],
                        'revoked' => 0,
                        'deleted' => false,
                    ],
                    'oauth2_refresh_token' => [
                        'access_token' => $clientInfo['test_active_user@example.com']['identifier'],
                        'expiry' => $clientInfo['test_active_user@example.com']['expiry'],
                        'revoked' => 0,
                        'deleted' => false,
                    ],
                ],
                'post_logout_data' => [
                    'oauth2_access_token' => [
                        'user_identifier' => 'test_active_user@example.com',
                        'identifier' => $clientInfo['test_active_user@example.com']['identifier'],
                        'expiry' => $clientInfo['test_active_user@example.com']['expiry'],
                        'revoked' => 1,
                        'deleted' => false,
                    ],
                    'oauth2_refresh_token' => [
                        'access_token' => $clientInfo['test_active_user@example.com']['identifier'],
                        'expiry' => $clientInfo['test_active_user@example.com']['expiry'],
                        'revoked' => 1,
                        'deleted' => false,
                    ],
                ],
            ], [
                'identifier' => 'inactive_user@example.com',
                'post_login_data' => [
                    'oauth2_access_token' => [
                        'user_identifier' => 'inactive_user@example.com',
                        'identifier' => $clientInfo['inactive_user@example.com']['identifier'],
                        'expiry' => $clientInfo['inactive_user@example.com']['expiry'],
                        'revoked' => 0,
                        'deleted' => false,
                    ],
                    'oauth2_refresh_token' => [
                        'access_token' => $clientInfo['inactive_user@example.com']['identifier'],
                        'expiry' => $clientInfo['inactive_user@example.com']['expiry'],
                        'revoked' => 0,
                        'deleted' => false,
                    ],
                ],
                'post_logout_data' => [
                    'oauth2_access_token' => [
                        'user_identifier' => 'inactive_user@example.com',
                        'identifier' => $clientInfo['inactive_user@example.com']['identifier'],
                        'expiry' => $clientInfo['inactive_user@example.com']['expiry'],
                        'deleted' => true,
                    ],
                    'oauth2_refresh_token' => [
                        'access_token' => $clientInfo['inactive_user@example.com']['identifier'],
                        'expiry' => $clientInfo['inactive_user@example.com']['expiry'],
                        'deleted' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $scopes
     * @param string $name
     * @param string $identifier
     * @param string $secret
     * @param \DateTimeImmutable|null $expiryDateTime
     * @param bool $requireRefreshToken
     * @param string $type
     *
     * @return string
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    private function newAccessToken(array $scopes, string $name = '', string $identifier = '', string $secret = '', ?\DateTimeImmutable $expiryDateTime = null, bool $requireRefreshToken = false, string $type = 'authorization_code'): string
    {
        if ($identifier === '') {
            $identifier = hash('md5', random_bytes(16));
        }
        if ($secret === '') {
            $secret = hash('sha512', random_bytes(32));
        }

        $client = new Client('', $identifier, $secret);
        $client->setScopes(...array_map(function ($s) {
            return new \League\Bundle\OAuth2ServerBundle\Model\Scope($s);
        }, $scopes));
        $this->clientManager->save($client);
        $clientEntity = $this->clientRepository->getClientEntity($identifier, $type, $secret);

        $accessTokenEntity = new AccessToken();
        $accessTokenEntity->setIdentifier($identifier);
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime($expiryDateTime ?? new \DateTimeImmutable('+1 days', new \DateTimeZone('Asia/Tokyo')));
        $accessTokenEntity->setUserIdentifier($name !== '' ? $name : 'admin');
        $accessTokenEntity->setPrivateKey(new CryptKey(self::$container->getParameter('kernel.project_dir') . '/app/PluginData/Api42/oauth/private.key'));

        array_walk($scopes, function ($s) use ($accessTokenEntity) {
            $scope = new Scope();
            $scope->setIdentifier($s);
            $accessTokenEntity->addScope($scope);
        });
        $this->accessTokenRepository->persistNewAccessToken($accessTokenEntity);

        // リフレッシュトークンを発行する場合
        if ($requireRefreshToken === true) {
            $this->newRefreshToken(accessTokenEntity: $accessTokenEntity, expiry: $expiryDateTime);
        }

        return $accessTokenEntity->__toString();
    }

    /**
     * リフレッシュトークンを発行する.
     *
     * @throws UniqueTokenIdentifierConstraintViolationException
     */
    private function newRefreshToken(AccessTokenEntityInterface $accessTokenEntity, \DateTimeImmutable $expiry): void
    {
        $refreshTokenEntity = new RefreshToken();
        $refreshTokenEntity->setIdentifier(hash('md5', random_bytes(16)));
        $refreshTokenEntity->setAccessToken($accessTokenEntity);
        $refreshTokenEntity->setExpiryDateTime($expiry);
        $this->refreshTokenRepository->persistNewRefreshToken($refreshTokenEntity);
    }
}
