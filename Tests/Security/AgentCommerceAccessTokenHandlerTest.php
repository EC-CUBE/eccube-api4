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

namespace Plugin\Api44\Tests\Security;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Plugin\Api44\Security\AgentCommerceAccessTokenHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * {@link AgentCommerceAccessTokenHandler} のユニットテスト (#188)。
 *
 * league の {@link ResourceServer} はモックし、検証成功時に付与 scope が UserBadge へ
 * 正しく載ること・検証失敗 (OAuthServerException) が BadCredentialsException に正規化される
 * ことを確認する (実トークンでの E2E は本体側 Job B が担当)。
 */
class AgentCommerceAccessTokenHandlerTest extends TestCase
{
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17Factory = new Psr17Factory();
    }

    public function testReturnsUserBadgeWithClientIdAndScopes(): void
    {
        $validated = $this->psr17Factory->createServerRequest('GET', 'https://localhost/')
            ->withAttribute('oauth_client_id', 'acp-client-1')
            ->withAttribute('oauth_scopes', ['acp:checkout', 'acp:catalog']);

        $handler = $this->createHandlerReturning($validated);
        $badge = $handler->getUserBadgeFrom('valid-jwt');

        self::assertSame('acp-client-1', $badge->getUserIdentifier(), 'subject は OAuth2 クライアント識別子');
        self::assertSame(
            ['acp:checkout', 'acp:catalog'],
            $badge->getAttributes()['scopes'],
            '付与 scope は UserBadge attributes の scopes に array で載る'
        );
    }

    public function testFallsBackToPlaceholderIdentifierWhenClientIdMissing(): void
    {
        $validated = $this->psr17Factory->createServerRequest('GET', 'https://localhost/')
            ->withAttribute('oauth_scopes', ['ucp:checkout']);

        $handler = $this->createHandlerReturning($validated);
        $badge = $handler->getUserBadgeFrom('valid-jwt');

        self::assertSame('oauth2-client', $badge->getUserIdentifier());
        self::assertSame(['ucp:checkout'], $badge->getAttributes()['scopes']);
    }

    public function testReturnsEmptyScopesWhenAttributeMissing(): void
    {
        $validated = $this->psr17Factory->createServerRequest('GET', 'https://localhost/')
            ->withAttribute('oauth_client_id', 'acp-client-1');

        $handler = $this->createHandlerReturning($validated);
        $badge = $handler->getUserBadgeFrom('valid-jwt');

        self::assertSame([], $badge->getAttributes()['scopes'], 'scope クレームが無ければ空配列');
    }

    public function testThrowsBadCredentialsWhenTokenInvalid(): void
    {
        $resourceServer = $this->createMock(ResourceServer::class);
        $resourceServer->method('validateAuthenticatedRequest')
            ->willThrowException(OAuthServerException::accessDenied('invalid token'));

        $handler = new AgentCommerceAccessTokenHandler($resourceServer, $this->psr17Factory);

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom('expired-or-tampered-jwt');
    }

    private function createHandlerReturning(ServerRequestInterface $validated): AgentCommerceAccessTokenHandler
    {
        $resourceServer = $this->createMock(ResourceServer::class);
        $resourceServer->method('validateAuthenticatedRequest')->willReturn($validated);

        return new AgentCommerceAccessTokenHandler($resourceServer, $this->psr17Factory);
    }
}
