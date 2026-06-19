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

use Eccube\Entity\Member;
use Eccube\Repository\MemberRepository;
use Eccube\Tests\EccubeTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope as ScopeValue;
use Plugin\Api44\Repository\McpTokenRepository;
use Plugin\Api44\Service\McpTokenService;

/**
 * MCP トークン発行サービスの契約テスト。
 *
 * 発行できる scope が MCP の read に限られ、 有効日数がプリセットに縛られることを担保する。
 * 発行したトークンが `/admin/mcp` firewall を通る/失効で 401 になる統合は、 MCP サーバ
 * (本体同梱) と Api44 が揃う本体の mcp ジョブ (McpTokenRevocationContractTest) で実走する。
 */
class McpTokenControllerTest extends EccubeTestCase
{
    private ?McpTokenService $mcpTokenService = null;
    private ?McpTokenRepository $mcpTokenRepository = null;
    private ?Member $member = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMcpPatClient();
        $this->mcpTokenService = static::getContainer()->get(McpTokenService::class);
        $this->mcpTokenRepository = static::getContainer()->get(McpTokenRepository::class);
        $this->member = static::getContainer()->get(MemberRepository::class)->findOneBy([]);
    }

    public function testIssueRejectsNonMcpScopesOnly(): void
    {
        // mcp:*:read 以外だけを要求した場合は発行できない
        $this->expectException(\InvalidArgumentException::class);
        $this->mcpTokenService->issue($this->member, 'bad', ['read', 'write'], 30);
    }

    public function testIssueFiltersToMcpScopes(): void
    {
        // mcp 以外が混ざっても mcp:*:read だけが採用される
        $this->mcpTokenService->issue($this->member, 'mixed', ['mcp:product:read', 'write'], 30);

        $mcpToken = $this->mcpTokenRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertSame(['mcp:product:read'], $mcpToken->getScopes());
    }

    public function testIssueRejectsInvalidExpireDays(): void
    {
        // プリセット外の有効日数 (フォームバイパス) は発行できない
        $this->expectException(\InvalidArgumentException::class);
        $this->mcpTokenService->issue($this->member, 'bad-expire', ['mcp:product:read'], 9999);
    }

    private function ensureMcpPatClient(): void
    {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        if (null !== $clientManager->find(McpTokenService::CLIENT_IDENTIFIER)) {
            return;
        }

        $client = new Client('MCP PAT', McpTokenService::CLIENT_IDENTIFIER, null);
        $client->setActive(true);
        $client->setGrants(new Grant('authorization_code'));
        $client->setScopes(...array_map(
            static fn (string $scope): ScopeValue => new ScopeValue($scope),
            McpTokenService::AVAILABLE_SCOPES,
        ));
        $clientManager->save($client);
    }
}
