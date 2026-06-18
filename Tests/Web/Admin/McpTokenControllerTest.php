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
use Plugin\Api44\Entity\McpToken;
use Plugin\Api44\Repository\McpTokenRepository;
use Plugin\Api44\Service\McpTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP トークン発行機能の契約テスト。
 *
 * 発行したトークンが `/admin/mcp` の firewall を通り、 失効すると即 401 になること、
 * 発行できる scope が MCP の read に限られることを担保する。
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

    public function testIssuedTokenIsAcceptedByMcpEndpoint(): void
    {
        $jwt = $this->mcpTokenService->issue($this->member, 'test token', ['mcp:product:read'], 30);

        $this->mcpRequest($jwt);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRevokedTokenReturns401(): void
    {
        $jwt = $this->mcpTokenService->issue($this->member, 'revoke me', ['mcp:product:read'], 30);

        // 発行直後は通る
        $this->mcpRequest($jwt);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // 失効すると同じトークンで 401
        $mcpToken = $this->mcpTokenRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertInstanceOf(McpToken::class, $mcpToken);
        $this->mcpTokenService->revoke($mcpToken);

        $this->mcpRequest($jwt);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
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

    /**
     * `/admin/mcp` に initialize を投げ、 firewall の通過可否を見る (handshake の成否)。
     */
    private function mcpRequest(string $bearerJwt): void
    {
        $adminRoute = static::getContainer()->getParameter('eccube_admin_route');
        $this->client->request(
            Request::METHOD_POST,
            '/'.$adminRoute.'/mcp',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json, text/event-stream',
                'HTTP_AUTHORIZATION' => 'Bearer '.$bearerJwt,
            ],
            content: '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","clientInfo":{"name":"t","version":"1"},"capabilities":{}}}',
        );
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
