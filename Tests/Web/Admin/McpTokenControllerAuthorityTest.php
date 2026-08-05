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

use Eccube\Entity\Master\Authority;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\Api44\Service\McpTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP トークン発行の認可ゲート契約テスト。
 *
 * 制限管理者 (authority != ADMIN) は URL 認可で管理画面を塞がれても stateless な mcp firewall では
 * 再評価されないため、 発行時に ADMIN を要求して第二の扉を塞ぐ (対話型同意フローと対称)。
 */
class McpTokenControllerAuthorityTest extends AbstractAdminWebTestCase
{
    public function testCreateForbiddenForNonAdminAuthority(): void
    {
        $member = $this->createMember();
        $member->setAuthority($this->entityManager->find(Authority::class, Authority::OWNER));
        $this->entityManager->flush();
        $this->loginTo($member);

        $url = $this->generateUrl('admin_api_mcp_token_new');
        $crawler = $this->client->request(Request::METHOD_GET, $url);

        $formData = [
            'label' => 'restricted-admin',
            'scopes' => [McpTokenService::AVAILABLE_SCOPES[0]],
            'expire' => McpTokenService::AVAILABLE_EXPIRE_DAYS[0],
        ];
        // CSRF が有効な環境では発行画面の hidden token を使う (無効ならフィールド自体が無い)
        $tokenNode = $crawler->filter('input[name="mcp_token[_token]"]');
        if ($tokenNode->count() > 0) {
            $formData['_token'] = $tokenNode->attr('value');
        }

        $this->client->request(Request::METHOD_POST, $url, ['mcp_token' => $formData]);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->client->getResponse()->getStatusCode(),
            '制限管理者 (authority != ADMIN) は MCP トークンを発行できない',
        );
    }
}
