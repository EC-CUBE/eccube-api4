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
use Symfony\Component\HttpFoundation\Response;
use League\Bundle\OAuth2ServerBundle\Model\Client;

class AuthorizationControllerTest extends AbstractAdminWebTestCase
{
    public function setUp(): void
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
                                       'redirect_uri' => (string) current($Client->getRedirectUris()),
                                       'response_type' => 'code',
                                       'scope' => 'read',
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
        $Client = $this->entityManager->getRepository(Client::class)->findOneBy([]);
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => (string) current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read',
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
                'scope' => 'read',
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
        $Client = $this->entityManager->getRepository(Client::class)->findOneBy([]);
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => (string) current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read',
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
                'scope' => 'read',
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

    /**
     * * @dataProvider xssSnippetsProvider
     */
    public function testRoutingAdminOauth2Authorize_XSS($snippet)
    {
        //基本設定＞店舗設定へ移動
        $shop_url = $this->generateUrl('admin_setting_shop');
        $this->client->request('GET', $shop_url);
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        //店舗名を入力
        $form = [
            'shop_name' => $snippet,
            'email01' => 'admin@example.com',
            'email03' => 'admin@example.com',
            'email04' => 'admin@example.com',
            'email02' => 'admin@example.com',
            '_token' => 'dummy',
        ];

        $this->client->enableProfiler();
        $crawler = $this->client->request(
            'POST',
            $shop_url,
            ['shop_master' => $form]
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirection());

        //OAuth画面へ移動
        /** @var Client $Client */
        $Client = $this->entityManager->getRepository(Client::class)->findOneBy([]);
        $authorize_url = $this->generateUrl(
            'oauth2_authorize',
            [
                'client_id' => $Client->getIdentifier(),
                'redirect_uri' => (string) current($Client->getRedirectUris()),
                'response_type' => 'code',
                'scope' => 'read write',
                'state' => 'xxx',
            ]
        );

        $crawler  = $this->client->request('GET', $authorize_url);

        // XSSが発火しないことを確認（XSSが発火するとscriptタグが文字列として表示されない）
        $accsessMessage = $crawler->filter('p.text-start')->eq(0);
        $this->assertEquals('アプリから'.$snippet.'へのアクセスを許可しますか？', $accsessMessage->text());

        // XSSが発火しないことを確認（XSSが発火するとscriptタグが文字列として表示されない）
        $readMessage = $crawler->filter('ul li.text-start')->eq(0);
        $this->assertEquals($snippet.'のデータに対する読み取り', $readMessage->text());

        // XSSが発火しないことを確認（XSSが発火するとscriptタグが文字列として表示されない）
        $writeMessage = $crawler->filter('ul li.text-start')->eq(1);
        $this->assertEquals($snippet.'のデータに対する書き込み', $writeMessage->text());

        // 店舗URLへ遷移することを確認
        $accsessLink = $accsessMessage->filter('a')->eq(0)->attr('href');
        $this->client->request('GET', $accsessLink);

        $crawler = $this->client->getCrawler();
        $title = $crawler->filter('title')->text();
        $this->assertEquals($snippet.' / TOPページ', $title);

        // 店舗URLへ遷移することを確認
        $readLink = $readMessage->filter('a')->eq(0)->attr('href');
        $this->client->request('GET', $readLink);

        $crawler = $this->client->getCrawler();
        $title = $crawler->filter('title')->text();
        $this->assertEquals($snippet.' / TOPページ', $title);

        // 店舗URLへ遷移することを確認
        $writeLink = $writeMessage->filter('a')->eq(0)->attr('href');
        $this->client->request('GET', $writeLink);

        $crawler = $this->client->getCrawler();
        $title = $crawler->filter('title')->text();
        $this->assertEquals($snippet.' / TOPページ', $title);
    }

    public function xssSnippetsProvider()
    {
        return [
            ['<script>alert(1)</script>'],
            ['<img src="javascript:alert(\'XSS\');">'],
            ['<body onload="alert(\'XSS\')">']
        ];
    }

    private function parseCallbackParams(Response $response)
    {
        $url = parse_url($response->headers->get('Location'));
        $redirectParams = [];
        parse_str($url['query'], $redirectParams);

        return $redirectParams;
    }
}
