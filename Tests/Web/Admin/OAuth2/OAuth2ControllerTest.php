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

namespace Plugin\Api\Tests\Web\Admin\OAuth2;

use Eccube\Common\Constant;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;

class OAuth2ControllerTest extends AbstractAdminWebTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testRoutingAdminOauth2Authorize_ログインしている場合は権限移譲確認画面を表示()
    {
        /** @var Client $Client */
        $Client = $this->entityManager->getRepository(Client::class)->findOneBy([]);

        $this->client->request('GET',
                               $this->generateUrl(
                                   'oauth2_authorize',
                                   [
                                       'client_id' => $Client->getIdentifier(),
                                       'redirect_uri' => current($Client->getRedirectUris()),
                                       'response_type' => 'code',
                                       'scope' => 'read',
                                       'state' => 'xxx'
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
        $Client = $this->entityManager->getRepository(Client::class)->findOneBy([]);
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read',
                'state' => 'xxx'
            ]
        );

        $this->client->request('GET', $authorize_url);

        $parameters = [
            'oauth_authorization' => [
                'client_id' => $Client->getIdentifier(),
                'client_secret' => $Client->getSecret(),
                'redirect_uri' => current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read',
                'state' => 'xxx',
                Constant::TOKEN_NAME => 'dummy',
            ],
        ];

        $crawler = $this->client->request(
            'POST', $authorize_url,
            $parameters
        );

        $this->assertTrue($this->client->getResponse()->isRedirection());
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
}
