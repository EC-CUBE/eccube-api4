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

namespace Plugin\Api44\Tests\Web;

use Eccube\Tests\EccubeTestCase;
use Plugin\Api44\Service\McpTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP OAuth ディスカバリ (.well-known / WWW-Authenticate / DCR) の契約テスト。
 *
 * クライアントが自動発見でき、 かつ公開エンドポイントが安全に縛られていることを実フローで担保する。
 */
class OAuthDiscoveryTest extends EccubeTestCase
{
    public function testProtectedResourceMetadataIsPublic(): void
    {
        $this->client->request(Request::METHOD_GET, '/.well-known/oauth-protected-resource');
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertStringEndsWith('/mcp', $data['resource']);
        $this->assertSame(McpTokenService::AVAILABLE_SCOPES, $data['scopes_supported']);
        $this->assertNotEmpty($data['authorization_servers']);
    }

    public function testAuthorizationServerMetadataIsPublic(): void
    {
        $this->client->request(Request::METHOD_GET, '/.well-known/oauth-authorization-server');
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('authorization_endpoint', $data);
        $this->assertArrayHasKey('token_endpoint', $data);
        $this->assertSame(['S256'], $data['code_challenge_methods_supported']);
        $this->assertSame(['none'], $data['token_endpoint_auth_methods_supported']);
        $this->assertStringEndsWith('/register', $data['registration_endpoint']);
    }

    public function testMcpUnauthorizedAdvertisesResourceMetadata(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/'.$this->adminRoute().'/mcp',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $header = (string) $response->headers->get('WWW-Authenticate');
        $this->assertStringContainsString('resource_metadata=', $header);
        $this->assertStringContainsString('/.well-known/oauth-protected-resource', $header);
    }

    public function testDcrRegistersLoopbackClientAndForcesScopeGrant(): void
    {
        // write / client_credentials を要求しても MCP read + authorization_code に固定されること
        $this->client->request(
            Request::METHOD_POST,
            '/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'client_name' => 'contract-test',
                'redirect_uris' => ['http://127.0.0.1:3334/oauth/callback'],
                'grant_types' => ['authorization_code', 'client_credentials'],
                'scope' => 'mcp:product:read write',
            ]),
        );
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertNotEmpty($data['client_id']);
        $this->assertSame(['authorization_code', 'refresh_token'], $data['grant_types']);
        $this->assertSame(implode(' ', McpTokenService::AVAILABLE_SCOPES), $data['scope']);
        $this->assertSame('none', $data['token_endpoint_auth_method']);
    }

    public function testDcrRejectsNonLoopbackHttpRedirect(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['redirect_uris' => ['http://evil.example.com/cb']]),
        );
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testDcrRejectsParserDifferentialRedirect(): void
    {
        // PHP parse_url は host=localhost と解釈するが、 ブラウザ (WHATWG) は evil.com に飛ぶ。
        // パーサ差異による host 詐称を fail-closed で拒否すること。
        $this->client->request(
            Request::METHOD_POST,
            '/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['redirect_uris' => ['http://evil.com\\@localhost/cb']]),
        );
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    private function adminRoute(): string
    {
        return (string) static::getContainer()->getParameter('eccube_admin_route');
    }
}
