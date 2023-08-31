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

namespace Plugin\Api42\Tests\Web\Admin\OAuth2Bundle;

use Eccube\Common\Constant;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Grant;
use League\Bundle\OAuth2ServerBundle\Model\RedirectUri;
use League\Bundle\OAuth2ServerBundle\Model\Scope;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;
use Symfony\Component\HttpFoundation\Response;
use League\Bundle\OAuth2ServerBundle\Model\Client;

class AuthorizationControllerTest extends AbstractAdminWebTestCase
{
    /** @var Client */
    private $OAuth2Client;

    public function setUp(): void
    {
        parent::setUp();
        $this->OAuth2Client = $this->createOAuth2Client();
    }

    public function testRoutingAdminOauth2Authorize_ログインしている場合は権限移譲確認画面を表示()
    {
        /** @var Client $Client */
        $Client = $this->OAuth2Client;

        $this->client->request('GET',
                               $this->generateUrl(
                                   'oauth2_authorize',
                                   [
                                       'client_id' => $Client->getIdentifier(),
                                       'redirect_uri' => (string) current($Client->getRedirectUris()),
                                       'response_type' => 'code',
                                       'scope' => 'read:Product',
                                       'state' => 'xxx',
                                   ]
                               )
        );

        // ログイン
        $this->assertEquals(
            200,
            $this->client->getResponse()->getStatusCode()
        );
    }

    public function testRoutingAdminOauth2Authorize_権限移譲を許可()
    {
        /** @var Client $Client */
        $Client = $this->OAuth2Client;
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => (string) current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read:Product',
                'state' => 'xxx',
            ]
        );

        $this->client->request('GET', $authorize_url);

        $parameters = [
            'oauth_authorization' => [
                'client_id' => $Client->getIdentifier(),
                'client_secret' => $Client->getSecret(),
                'redirect_uri' => current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read:Product',
                'state' => 'xxx',
                'approve' => '',
                Constant::TOKEN_NAME => 'dummy',
            ],
        ];

        $this->client->request(
            'POST', $authorize_url,
            $parameters
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirection());

        $callbackParams = $this->parseCallbackParams($response);

        self::assertFalse(isset($callbackParams['error']));
        self::assertTrue(isset($callbackParams['code']));
    }

    public function testRoutingAdminOauth2Authorize_権限移譲を許可しない()
    {
        /** @var Client $Client */
        $Client = $this->OAuth2Client;
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => (string) current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read:Product',
                'state' => 'xxx',
            ]
        );

        $this->client->request('GET', $authorize_url);

        $parameters = [
            'oauth_authorization' => [
                'client_id' => $Client->getIdentifier(),
                'client_secret' => $Client->getSecret(),
                'redirect_uri' => current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read:Product',
                'state' => 'xxx',
                'deny' => '',
                Constant::TOKEN_NAME => 'dummy',
            ],
        ];

        $this->client->request(
            'POST', $authorize_url,
            $parameters
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirection());

        $redirectUrl = $response->headers->get('Location');
        self::assertStringStartsWith((string) $Client->getRedirectUris()[0], $redirectUrl);

        $callbackParams = $this->parseCallbackParams($response);
        self::assertEquals('access_denied', $callbackParams['error']);
    }

    public function testRoutingAdminOauth2Authorize_権限移譲を許可_パラメータが足りない場合()
    {
        $parameters = [
            'oauth_authorization' => [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => '',
                'response_type' => '',
                'state' => '',
                'scope' => '',
                Constant::TOKEN_NAME => '',
            ],
        ];

        $this->client->request(
            'POST', $this->generateUrl('oauth2_authorize'),
            $parameters
        );

        $this->assertFalse($this->client->getResponse()->isRedirection());
    }

    private function parseCallbackParams(Response $response)
    {
        $url = parse_url($response->headers->get('Location'));
        $redirectParams = [];
        parse_str($url['query'], $redirectParams);

        return $redirectParams;
    }

    private function createOAuth2Client(): Client
    {
        $client_id = hash('md5', random_bytes(16));
        $client_secret = hash('sha256', random_bytes(32));
        $Client = new Client('', $client_id, $client_secret);
        $Client
            ->setScopes(new Scope('read:Product'))
            ->setRedirectUris(new RedirectUri('http://127.0.0.1:8000/'))
            ->setGrants(
                new Grant(OAuth2Grants::AUTHORIZATION_CODE),
                new Grant(OAuth2Grants::REFRESH_TOKEN)
            )
            ->setActive(true);
        $clientManager = self::$container->get(ClientManagerInterface::class);
        $clientManager->save($Client);

        return $Client;
    }
}
