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

namespace Plugin\Api44\Tests\Web\Admin;

use Eccube\Tests\Web\AbstractWebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginControllerTest extends AbstractWebTestCase
{
    public function testRoutingAdminLogin(): void
    {
        $this->client->request('GET', $this->generateUrl('admin_login'));

        // ログイン
        $this->assertEquals(
            200,
            $this->client->getResponse()->getStatusCode()
        );
    }

    public function testRoutingAdminLoginCheck(): void
    {
        // see https://stackoverflow.com/a/38661340/4956633
        $this->client->request(
            'POST', $this->generateUrl('admin_login'),
            [
                'login_id' => 'admin',
                'password' => 'password',
                '_csrf_token' => 'dummy',
            ]
        );

        $this->assertNotNull(self::getContainer()->get('security.token_storage')->getToken(), 'ログインしているかどうか');
    }

    public function testRoutingAdminLoginログインしていない場合はログイン画面を表示(): void
    {
        $this->client->request('GET', $this->generateUrl('admin_homepage'));

        // ログイン
        self::assertTrue($this->client->getResponse()->isRedirect(
            $this->generateUrl('admin_login', [], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function testRoutingAdminOauth2Authorizeログインしていない場合はログイン画面を表示(): void
    {
        $this->client->request('GET', $this->generateUrl('oauth2_authorize'));

        // ログイン
        self::assertTrue($this->client->getResponse()->isRedirect(
            $this->generateUrl('admin_login', [], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function testRoutingOauth2Authorizeログインしていない場合はログイン画面を表示(): void
    {
        $this->client->request('GET', $this->generateUrl('oauth2_authorize'));

        // ログイン
        self::assertTrue($this->client->getResponse()->isRedirect(
            $this->generateUrl('admin_login', [], UrlGeneratorInterface::ABSOLUTE_URL)));
    }
}
