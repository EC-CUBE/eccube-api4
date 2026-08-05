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

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\OAuth2Grants;
use Plugin\Api44\Form\Type\Admin\AgentCommerceClientType;

/**
 * エージェントコマース (ACP/UCP) 用クライアント登録の契約テスト。
 *
 * grant が client_credentials に固定され、 protocol を跨いだ scope や
 * 会員同意が前提の scope (ucp:identity) を付与できないことを担保する。
 */
class AgentCommerceClientControllerTest extends AbstractAdminWebTestCase
{
    public function testAcpFormOffersOnlyAcpScopes(): void
    {
        $crawler = $this->client->request('GET', $this->generateUrl('admin_api_oauth_acp_new'));

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertSame(
            ['acp:checkout', 'acp:catalog'],
            $this->scopeChoices($crawler),
            'ACP の画面には acp: の scope だけを提示する'
        );
    }

    public function testUcpFormExcludesIdentityScope(): void
    {
        $crawler = $this->client->request('GET', $this->generateUrl('admin_api_oauth_ucp_new'));

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        // ucp:identity は Customer subject の authorization_code が前提 (eccube-api4#189) のため提示しない
        $this->assertSame(['ucp:checkout', 'ucp:cart', 'ucp:catalog'], $this->scopeChoices($crawler));
    }

    public function testFormHasNoRedirectUriAndGrantChoice(): void
    {
        $crawler = $this->client->request('GET', $this->generateUrl('admin_api_oauth_acp_new'));

        // grant は client_credentials 固定、 redirect_uri は不使用なので入力させない
        $this->assertCount(0, $crawler->filter('input[name="api_admin_agent_commerce_client[redirect_uris]"]'));
        $this->assertCount(0, $crawler->filter('input[name^="api_admin_agent_commerce_client[grants]"]'));
    }

    public function testCreateAcpClientFixesGrantToClientCredentials(): void
    {
        $identifier = 'acptestclient'.random_int(1000, 9999);
        $this->submitCreate('admin_api_oauth_acp_new', $identifier, ['acp:checkout'], 'ChatGPT');

        $client = $this->findClient($identifier);

        $this->assertNotNull($client, 'ACP クライアントが登録されること');
        $this->assertSame('ChatGPT', $client->getName(), 'どの事業者向けかを一覧で追えるよう名称を保持する');
        $this->assertSame(
            [OAuth2Grants::CLIENT_CREDENTIALS],
            array_map(strval(...), $client->getGrants()),
            'grant は client_credentials に固定される (authorization_code / refresh_token を持たない)'
        );
        $this->assertSame(['acp:checkout'], array_map(strval(...), $client->getScopes()));
    }

    public function testCreateShowsSecretOnceAndNotInList(): void
    {
        $identifier = 'acpsecretclient'.random_int(1000, 9999);
        $secret = 'acponetimesecret';

        $crawler = $this->client->request('POST', $this->generateUrl('admin_api_oauth_acp_new'), [
            'api_admin_agent_commerce_client' => [
                'name' => 'ChatGPT',
                'identifier' => $identifier,
                'secret' => $secret,
                'scopes' => ['acp:checkout'],
                '_token' => 'dummy',
            ],
        ]);

        // 発行直後の画面でだけ平文を提示する (redirect すると失われる)
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertSame($secret, trim($crawler->filter('#agent_commerce_client_secret')->text()));
        $this->assertStringContainsString(
            'no-store',
            (string) $this->client->getResponse()->headers->get('Cache-Control'),
            '認証情報を含む画面はキャッシュさせない'
        );

        // 一覧では再表示しない (league が初回利用時に保存値をハッシュへ差し替えるため)
        $crawler = $this->client->request('GET', $this->generateUrl('admin_api_oauth'));
        $row = $crawler->filter('#client-'.$identifier);
        $this->assertCount(1, $row);
        $this->assertStringNotContainsString($secret, $row->html());
        $this->assertCount(0, $row->filter('input.copy-secret[value="'.$secret.'"]'));
    }

    public function testCreateRejectsScopeOfAnotherProtocol(): void
    {
        $identifier = 'acpcrossclient'.random_int(1000, 9999);
        // ACP の導線へ UCP の scope を送り込む (フォームバイパス)
        $this->submitCreate('admin_api_oauth_acp_new', $identifier, ['ucp:checkout']);

        $this->assertNull($this->findClient($identifier), 'protocol を跨いだ scope では登録されない');
    }

    public function testIdentityScopeIsNotGrantableFromThisFlow(): void
    {
        $identifier = 'ucpidentityclient'.random_int(1000, 9999);
        $this->submitCreate('admin_api_oauth_ucp_new', $identifier, ['ucp:identity']);

        $this->assertNull(
            $this->findClient($identifier),
            'ucp:identity は会員同意 (Customer authorization_code) が前提のため client_credentials では付与できない'
        );
        $this->assertNotContains('ucp:identity', AgentCommerceClientType::PROTOCOL_SCOPES['ucp']);
    }

    /**
     * @return list<string>
     */
    private function scopeChoices(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('input[name="api_admin_agent_commerce_client[scopes][]"]')
            ->each(static fn (\Symfony\Component\DomCrawler\Crawler $node): string => (string) $node->attr('value'));
    }

    /**
     * @param list<string> $scopes
     */
    private function submitCreate(string $route, string $identifier, array $scopes, string $name = 'agent'): void
    {
        $this->client->request('POST', $this->generateUrl($route), [
            'api_admin_agent_commerce_client' => [
                'name' => $name,
                'identifier' => $identifier,
                'secret' => 'agentclientsecret',
                'scopes' => $scopes,
                '_token' => 'dummy',
            ],
        ]);
    }

    private function findClient(string $identifier): ?Client
    {
        // Web リクエスト側で永続化されたクライアントを読むため、 テスト側の identity map を捨てる
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Client::class)->findOneBy(['identifier' => $identifier]);
    }
}
