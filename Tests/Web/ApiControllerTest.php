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
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use League\Bundle\OAuth2ServerBundle\Entity\Scope;
use League\Bundle\OAuth2ServerBundle\Manager\Doctrine\ClientManager;
use League\Bundle\OAuth2ServerBundle\Model\Client;

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

    public function setUp(): void
    {
        parent::setUp();
        $this->clientManager = self::$container->get(ClientManager::class);
        $this->clientRepository = self::$container->get(ClientRepositoryInterface::class);
        $this->accessTokenRepository = self::$container->get(AccessTokenRepositoryInterface::class);
        $this->authorizationServer = self::$container->get(AuthorizationServer::class);
    }

    /**
     * @dataProvider permissionProvider
     */
    public function testPermission($scopes, $query, $expectedErrorMessage = null)
    {
        $headers = [];
        if ($scopes) {
            $token = $this->newAccessToken($scopes);
            $headers = ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
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
            [['read:Product'],  '{ product(id:1) { id, name } }'],
            [['read:Product'],  '{ customer(id:1) { id } }', 'Cannot query field "customer" on type "Query".'],
            [['read:Product', 'read:Customer'],  '{ customer(id:1) { id } }'],
            [['read:Product'],  '{ product(id:1) { id, name, ProductClasses { id } } }', 'Cannot query field "ProductClasses" on type "Product".'],
            [['read:Product', 'read:ProductClass'],  '{ product(id:1) { id, name, ProductClasses { id } } }'],
            [null,  '{ product(id:1) { id, name } }'],
        ];
    }

    private function newAccessToken($scopes)
    {
        $identifier = hash('md5', random_bytes(16));
        $secret = hash('sha512', random_bytes(32));

        $client = new Client('', $identifier, $secret);
        $client->setScopes(...array_map(function ($s) {
            return new \League\Bundle\OAuth2ServerBundle\Model\Scope($s);
        }, $scopes));
        $this->clientManager->save($client);
        $clientEntity = $this->clientRepository->getClientEntity($identifier, 'authorization_code', $secret);

        $accessTokenEntity = new AccessToken();
        $accessTokenEntity->setIdentifier($identifier);
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime(new \DateTimeImmutable('+1 days', new \DateTimeZone('Asia/Tokyo')));
        $accessTokenEntity->setUserIdentifier('admin');
        $accessTokenEntity->setPrivateKey(new CryptKey(self::$container->getParameter('kernel.project_dir').'/app/PluginData/Api42/oauth/private.key'));

        array_walk($scopes, function ($s) use ($accessTokenEntity) {
            $scope = new Scope();
            $scope->setIdentifier($s);
            $accessTokenEntity->addScope($scope);
        });
        $this->accessTokenRepository->persistNewAccessToken($accessTokenEntity);

        return $accessTokenEntity->__toString();
    }
}
