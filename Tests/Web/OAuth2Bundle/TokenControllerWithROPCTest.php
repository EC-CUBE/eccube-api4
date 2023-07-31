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

namespace Plugin\Api42\Tests\Web\OAuth2Bundle;

use Eccube\Entity\Customer;
use Eccube\Tests\Web\AbstractWebTestCase;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\Model\Grant;
use League\Bundle\OAuth2ServerBundle\Model\RedirectUri;
use League\Bundle\OAuth2ServerBundle\Model\Scope;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;

class TokenControllerWithROPCTest extends AbstractWebTestCase
{
    protected ?Client $OAuth2Client;
    protected ?Customer $Customer;

    public function setUp(): void
    {
        parent::setUp();
        $this->OAuth2Client = $this->createOAuth2Client();
        $this->Customer = $this->createCustomer();
    }

    public function testGetInstance()
    {
        $this->client->request(
            'POST',
            $this->generateUrl('oauth2_token'),
            [
                'grant_type' => OAuth2Grants::PASSWORD,
                'client_id' => $this->OAuth2Client->getIdentifier(),
                'username' => $this->Customer->getEmail(),
                'password' => 'password',
                'scope' => 'read write'
            ]
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('access_token', $response);
        self::assertArrayHasKey('refresh_token', $response);
        self::assertSame('Bearer', $response['token_type']);
        self::assertSame(3600, $response['expires_in']);

        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($response['access_token']);

        self::assertTrue($token->isRelatedTo($this->Customer->getEmail()), 'Token is not related to customer(sub)');
        self::assertFalse($token->isExpired(new \DateTimeImmutable('+3590 second')), 'Token is expired(exp)');
        self::assertTrue($token->isPermittedFor($this->OAuth2Client->getIdentifier()), 'Token is not permitted for client(aud)');
        self::assertTrue($token->hasBeenIssuedBefore(new \DateTimeImmutable('+1 second')), 'Token has not been issued before(iat)');
        self::assertTrue($token->isMinimumTimeBefore(new \DateTimeImmutable('+1 second')), 'Token is not minimum time before(nbf)');
    }

    protected function createOAuth2Client(): Client
    {
        $client_id = hash('md5', random_bytes(16));
        $Client = new Client('', $client_id, null); // public client
        $Client
            ->setScopes(
                new Scope('read'),
                new Scope('write')
            )
            ->setRedirectUris(new RedirectUri('http://127.0.0.1:8000/'))
            ->setGrants(
                new Grant(OAuth2Grants::PASSWORD),
                new Grant(OAuth2Grants::REFRESH_TOKEN)
            )
            ->setActive(true);

        $clientManager = self::$container->get(ClientManagerInterface::class);
        $clientManager->save($Client);

        return $Client;
    }
}
