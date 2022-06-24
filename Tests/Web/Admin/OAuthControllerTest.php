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

namespace Plugin\Api42\Tests\Web\Admin;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\Doctrine\ClientManager;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;

class OAuthControllerTest extends AbstractAdminWebTestCase
{
    /**
     * @var ClientManager
     */
    protected $clientManager;

    /**
     * @{@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->clientManager = self::$container->get(ClientManager::class);
    }

    public function testRoutingAdminSettingSystemOAuth2Client()
    {
        $this->client->request('GET', $this->generateUrl('admin_api_oauth'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testRoutingAdminSettingSystemOAuth2ClientCreate()
    {
        $this->client->request('GET', $this->generateUrl('admin_api_oauth_new'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testRoutingAdminSettingSystemOAuth2ClientDelete()
    {
        // before
        $identifier = hash('md5', random_bytes(16));
        $secret = hash('sha512', random_bytes(32));
        $client = new Client('', $identifier, $secret);
        $this->clientManager->save($client);

        // main
        $redirectUrl = $this->generateUrl('admin_api_oauth');
        $this->client->request('DELETE',
            $this->generateUrl('admin_api_oauth_delete', ['identifier' => $identifier])
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($redirectUrl));
        $this->assertNull($this->clientManager->find($identifier));

        $crawler = $this->client->followRedirect();
        $this->assertMatchesRegularExpression('/削除しました/u', $crawler->filter('div.alert-success')->text());
    }

    public function testOAuth2ClientCreateSubmit()
    {
        // before
        $formData = $this->createFormData();

        // main
        $this->client->request('POST',
            $this->generateUrl('admin_api_oauth_new'),
            [
                'api_admin_client' => $formData,
            ]
        );

        $client = $this->clientManager->find($formData['identifier']);

        $redirectUrl = $this->generateUrl('admin_api_oauth');
        $this->assertTrue($this->client->getResponse()->isRedirect($redirectUrl));

        $this->actual = $client->getIdentifier();
        $this->expected = $formData['identifier'];
        $this->verify();

        $scopes = $client->getScopes();
        $this->assertTrue(in_array('read', $scopes));
        $this->assertTrue(in_array('write', $scopes));

        // authorization code grant が選択されていた場合には refresh token grant も付与される
        $grants = $client->getGrants();
        $this->assertTrue(in_array(OAuth2Grants::AUTHORIZATION_CODE, $grants));
        $this->assertTrue(in_array(OAuth2Grants::REFRESH_TOKEN, $grants));

        $crawler = $this->client->followRedirect();
        $this->assertMatchesRegularExpression('/保存しました/u', $crawler->filter('div.alert-success')->text());
    }

    public function testOAuth2ClientCreateSubmitFail()
    {
        // before
        $formData = $this->createFormData();
        $formData['identifier'] = '';

        // main
        $crawler = $this->client->request('POST',
            $this->generateUrl('admin_api_oauth_new'),
            [
                'api_admin_client' => $formData,
            ]
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->assertMatchesRegularExpression('/入力されていません。/u', $crawler->filter('span.form-error-message')->text());
    }

    public function testOAuth2ClientDeleteIdentifierNotFound()
    {
        // before
        $identifier = hash('md5', random_bytes(16));

        // main
        $redirectUrl = $this->generateUrl('admin_api_oauth');
        $this->client->request('DELETE',
            $this->generateUrl('admin_api_oauth_delete', ['identifier' => $identifier])
        );

        $this->assertTrue($this->client->getResponse()->isRedirect($redirectUrl));

        $crawler = $this->client->followRedirect();
        $this->assertMatchesRegularExpression('/既に削除されています/u', $crawler->filter('div.alert-danger')->text());
    }

    protected function createFormData()
    {
        return [
            '_token' => 'dummy',
            'identifier' => hash('md5', random_bytes(16)),
            'secret' => hash('sha512', random_bytes(32)),
            'scopes' => ['read', 'write'],
            'redirect_uris' => 'http://127.0.0.1:8000/',
            'grants' => ['authorization_code'],
        ];
    }
}
