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

namespace Plugin\Api44\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * MCP の OAuth ディスカバリ用メタデータ (RFC 9728 / RFC 8414) と
 * 401 の WWW-Authenticate 値を一元生成する。 URL 組み立てを 1 箇所に集約し、
 * canonical resource URI を PRM の resource と WWW-Authenticate の 2 者で一致させる。
 * JWT の aud はここでは扱わない。 league の AccessTokenTrait が client 識別子を入れるため、
 * resource URI にはならない (resource へ寄せるには RFC 8707 相当の実装が別途必要)。
 */
class OAuthMetadataBuilder
{
    /**
     * @var list<string> 配信する scope (ワイルドカードでなく具体値)。 全箇所で同一集合を使う
     */
    public const SCOPES = McpTokenService::AVAILABLE_SCOPES;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $adminRoute,
    ) {
    }

    /**
     * 現在のリクエストから `<scheme>://<host><base-path>` を得る (リバースプロキシ対応は trusted proxies 設定に従う)。
     * base path を含めるのは、 サブディレクトリ配下 (例 /shop) に公開したとき resource / 各 endpoint の
     * URL が base path 落ちで壊れるのを防ぐため。
     */
    public function baseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return '';
        }

        return $request->getSchemeAndHttpHost().$request->getBaseUrl();
    }

    /**
     * MCP サーバの canonical URI (trailing-slash なし)。 PRM の resource と一致させる。
     */
    public function resourceUri(): string
    {
        return $this->baseUrl().'/'.$this->adminRoute.'/mcp';
    }

    /**
     * Protected Resource Metadata の絶対 URL (host ルート形)。
     */
    public function protectedResourceMetadataUrl(): string
    {
        return $this->baseUrl().'/.well-known/oauth-protected-resource';
    }

    /**
     * 401 の WWW-Authenticate ヘッダ値 (RFC 6750 + RFC 9728)。
     */
    public function wwwAuthenticate(): string
    {
        return sprintf(
            'Bearer resource_metadata="%s", scope="%s"',
            $this->protectedResourceMetadataUrl(),
            implode(' ', self::SCOPES),
        );
    }

    /**
     * RFC 9728 Protected Resource Metadata.
     *
     * @return array<string, mixed>
     */
    public function protectedResourceMetadata(): array
    {
        return [
            'resource' => $this->resourceUri(),
            'authorization_servers' => [$this->baseUrl()],
            'scopes_supported' => self::SCOPES,
            'bearer_methods_supported' => ['header'],
        ];
    }

    /**
     * RFC 8414 Authorization Server Metadata.
     *
     * @return array<string, mixed>
     */
    public function authorizationServerMetadata(): array
    {
        $base = $this->baseUrl();

        return [
            'issuer' => $base,
            'authorization_endpoint' => $base.'/'.$this->adminRoute.'/authorize',
            'token_endpoint' => $base.'/token',
            'registration_endpoint' => $base.'/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => self::SCOPES,
            // RFC 9207 対応済み。 認可応答に issuer (=この issuer) を付与する (AuthorizationResponseIssListener)
            'authorization_response_iss_parameter_supported' => true,
        ];
    }
}
