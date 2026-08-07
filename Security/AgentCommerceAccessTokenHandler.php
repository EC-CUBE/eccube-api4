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

namespace Plugin\Api44\Security;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * エージェントコマース (ACP/UCP) のインバウンド OAuth2 Bearer トークンを検証する
 * Symfony 標準 {@link AccessTokenHandlerInterface} の実装 (eccube-api4#188)。
 *
 * EC-CUBE 本体の AgentCommerceOAuth2Authenticator はこの口にのみ依存し (具象 api4 には非依存)、
 * 本ハンドラが league の {@link ResourceServer} を用いて Bearer トークン (JWT) を検証する:
 * 公開鍵による署名検証・有効期限・失効 (AccessTokenRepository) を league がまとめて担保する。
 *
 * 付与 scope は {@link UserBadge} の attributes に `scopes` (array<string>) として載せる。
 * 本体はこの attributes から scope を取り出し ScopeRegistry で protocol×capability を照合する。
 * subject (UserBadge identifier) は OAuth2 クライアント識別子 (client_credentials のため会員は伴わない)。
 *
 * **`role_prefix: ROLE_OAUTH2_` による scope → role 変換は経由しない**。 返す InMemoryUser には
 * `ROLE_OAUTH2_CLIENT` だけを載せるため、 `ROLE_OAUTH2_ACP:CHECKOUT` のようなロールは生成されない。
 * GraphQL / MCP 経路は role ベースで認可するのに対し、 エージェントコマースは attributes の scope を
 * 本体が直接照合する流儀になる。 `access_control` や `is_granted()` で `ROLE_OAUTH2_<SCOPE>` を
 * 期待しないこと (認可は本体の AgentCommerceOAuth2Authenticator 側にある)。
 */
final class AgentCommerceAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly ResourceServer $resourceServer,
        private readonly ServerRequestFactoryInterface $serverRequestFactory,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        // league の ResourceServer は PSR-7 リクエスト前提のため、Authorization ヘッダだけ持つ
        // 最小のリクエストを組み立てて検証に通す。
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'https://localhost/')
            ->withHeader('Authorization', 'Bearer '.$accessToken);

        try {
            $validated = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            // 署名不正・期限切れ・失効・形式不正はすべて認証失敗 (401) に正規化する。
            throw new BadCredentialsException('Invalid OAuth2 access token.', 0, $e);
        }

        $clientId = (string) ($validated->getAttribute('oauth_client_id') ?? '');

        $rawScopes = $validated->getAttribute('oauth_scopes');
        $scopes = is_array($rawScopes)
            ? array_values(array_map(static fn ($scope): string => (string) $scope, $rawScopes))
            : [];

        return new UserBadge(
            '' !== $clientId ? $clientId : 'oauth2-client',
            // client_credentials は会員を伴わないため、provider 探索を避けて軽量ユーザーを返す。
            // 本体は identifier と attributes のみ参照するが、getUser() 呼び出しにも備える。
            static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null, ['ROLE_OAUTH2_CLIENT']),
            ['scopes' => $scopes],
        );
    }
}
