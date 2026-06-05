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

namespace Plugin\Api44\Tests\Web;

use Eccube\Common\EccubeConfig;
use Eccube\Tests\Web\AbstractWebTestCase;
use League\Bundle\OAuth2ServerBundle\Entity\AccessToken;
use League\Bundle\OAuth2ServerBundle\Entity\Scope;
use League\Bundle\OAuth2ServerBundle\Manager\Doctrine\ClientManager;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class ApiControllerTest extends AbstractWebTestCase
{
    /** @var ClientManager */
    private ?ClientManager $clientManager = null;

    /** @var ClientRepositoryInterface */
    private ?ClientRepositoryInterface $clientRepository = null;

    /** @var AccessTokenRepositoryInterface */
    private ?AccessTokenRepositoryInterface $accessTokenRepository = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->clientManager = self::getContainer()->get(ClientManager::class);
        $this->clientRepository = self::getContainer()->get(ClientRepositoryInterface::class);
        $this->accessTokenRepository = self::getContainer()->get(AccessTokenRepositoryInterface::class);
    }

    /**
     * @param string[] $scopes
     */
    #[DataProvider('permissionProvider')]
    public function testPermission(array $scopes, string $query, ?string $expectedErrorMessage = null): void
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

    /**
     * @return string[][]|string[][][]
     */
    public static function permissionProvider(): array
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

    /**
     * @param string[] $scopes
     */
    private function newAccessToken(array $scopes): string
    {
        $identifier = hash('md5', random_bytes(16));
        $secret = hash('sha512', random_bytes(32));

        $client = new Client('', $identifier, $secret);
        $client->setScopes(...array_map(function (string $s): \League\Bundle\OAuth2ServerBundle\ValueObject\Scope {
            return new \League\Bundle\OAuth2ServerBundle\ValueObject\Scope($s);
        }, $scopes));
        $this->clientManager->save($client);
        $clientEntity = $this->clientRepository->getClientEntity($identifier);

        $accessTokenEntity = new AccessToken();
        $accessTokenEntity->setIdentifier($identifier);
        $accessTokenEntity->setClient($clientEntity);
        $accessTokenEntity->setExpiryDateTime(new \DateTimeImmutable('+1 days', new \DateTimeZone('Asia/Tokyo')));
        $accessTokenEntity->setUserIdentifier('admin');
        $accessTokenEntity->setPrivateKey(new CryptKey(self::getContainer()->get(EccubeConfig::class)->get('kernel.project_dir').'/app/PluginData/Api44/oauth/private.key'));
        $accessTokenEntity->initJwtConfiguration();

        array_walk($scopes, function (string $s) use ($accessTokenEntity): void {
            $scope = new Scope();
            $scope->setIdentifier($s);
            $accessTokenEntity->addScope($scope);
        });
        $this->accessTokenRepository->persistNewAccessToken($accessTokenEntity);

        return $accessTokenEntity->toString();
    }
}
