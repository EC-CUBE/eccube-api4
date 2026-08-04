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

namespace Plugin\Api44\Tests\Web\Admin\OAuth2Bundle;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * PKCE(S256) 必須が「宣言」でなく「強制」として効いていることを挙動で縛る。
 *
 * secret を持たない public クライアントが code_challenge 無しで認可要求すると拒否されることを確認する。
 * (config 値の assert では library 既定に頼っているだけかを区別できないため、 実挙動で確認する)
 */
class PkceEnforcementTest extends AbstractAdminWebTestCase
{
    public function testAuthorizeRejectsPublicClientWithoutCodeChallenge(): void
    {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        // secret = null で public クライアント (DCR 発行クライアントと同型)
        $client = new Client('Public No PKCE', 'publicnopkce', null);
        $client->setActive(true);
        $client->setRedirectUris(new RedirectUri('http://127.0.0.1/callback'));
        $client->setGrants(new Grant('authorization_code'));
        $client->setScopes(new Scope('read'));
        $clientManager->save($client);

        $this->client->request('GET', $this->generateUrl('oauth2_authorize', [
            'client_id' => 'publicnopkce',
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => 'read',
            'state' => 'xxx',
        ]));

        $response = $this->client->getResponse();
        // 同意画面 (200) には進まず、 league が PKCE 必須で invalid_request として弾く
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('invalid_request', $data['error']);
        $this->assertStringContainsString(
            'Code challenge must be provided for public clients',
            (string) ($data['hint'] ?? ''),
        );
    }
}
