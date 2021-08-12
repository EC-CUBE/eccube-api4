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

namespace Plugin\Api\Tests\Web;

use DateTime;
use Eccube\Tests\Web\AbstractWebTestCase;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Trikoder\Bundle\OAuth2Bundle\League\Entity\AccessToken;
use Trikoder\Bundle\OAuth2Bundle\League\Entity\Scope;
use Trikoder\Bundle\OAuth2Bundle\Manager\Doctrine\ClientManager;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;

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

    public function setUp()
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
        $token = $this->newAccessToken($scopes);
        $this->client->request('POST', $this->generateUrl('api'), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], json_encode(['query' => $query]));

        self::assertEquals(200, $this->client->getResponse()->getStatusCode());

        $payload = json_decode($this->client->getResponse()->getContent(), true);

        if ($expectedErrorMessage) {
            self::assertEquals($expectedErrorMessage, $payload['errors'][0]['message']);
        } else {
            self::assertFalse(isset($payload['errors']));
        }
    }

    public function permissionProvider()
    {
        $query = '{ product(id:1) { id, name } }';
        $mutation = 'mutation { updateProductStock(code: "sand-01", stock: 10, stock_unlimited:false) { id } }';

        return [
            [['read'],  $query],
            [['write'], $query, 'Insufficient permission. (read)'],
            [['read', 'write'], $query],
            [['read'], $mutation, 'Insufficient permission. (read,write)'],
            [['write'], $mutation, 'Insufficient permission. (read,write)'],
            [['read', 'write'], $mutation],
        ];
    }

    private function newAccessToken($scopes)
    {
        $identifier = hash('md5', random_bytes(16));
        $secret = hash('sha512', random_bytes(32));

        $client = new Client($identifier, $secret);
        $client->setScopes(...array_map(function ($s) {
            return new \Trikoder\Bundle\OAuth2Bundle\Model\Scope($s);
        }, $scopes));
        $this->clientManager->save($client);
        $clientEntity = $this->clientRepository->getClientEntity($identifier, 'authorization_code', $secret);

        $accessTokenEntity = new AccessToken();
        $accessTokenEntity->setIdentifier($identifier);
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime(new DateTime('+1 hour'));
        $accessTokenEntity->setUserIdentifier('admin');
        array_walk($scopes, function ($s) use ($accessTokenEntity) {
            $scope = new Scope();
            $scope->setIdentifier($s);
            $accessTokenEntity->addScope($scope);
        });
        $this->accessTokenRepository->persistNewAccessToken($accessTokenEntity);

        $rc = new \ReflectionClass($this->authorizationServer);
        $property = $rc->getProperty('privateKey');
        $property->setAccessible(true);

        return $accessTokenEntity->convertToJWT($property->getValue($this->authorizationServer));
    }
}
