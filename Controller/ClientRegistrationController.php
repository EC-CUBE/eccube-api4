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

namespace Plugin\Api44\Controller;

use Eccube\Controller\AbstractController;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Plugin\Api44\Entity\DcrClient;
use Plugin\Api44\Service\McpTokenService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7591 動的クライアント登録 (DCR)。 MCP クライアント (Claude Desktop / mcp-remote) が
 * 自分の redirect_uri を登録して client_id を得るための公開エンドポイント。
 *
 * セキュリティ:
 *   - 公開だが client_id を作るだけ。 データ到達には別途 /authorize での管理ログイン+同意が必須。
 *   - redirect_uri は https または loopback (localhost/127.0.0.1/[::1]) のみ許可。 さらにパーサ差異
 *     (PHP parse_url とブラウザの WHATWG URL) による host 詐称を防ぐため userinfo(@)/バックスラッシュ/
 *     fragment を含む URI は拒否 (fail-closed)。
 *   - 付与する grant/scope は MCP read に固定 (クライアント要求の write/他 grant は無視)。
 *   - IP 単位 + グローバルの 2 段レート制限で濫用 (死蔵クライアント量産・IP 偽装回避) を抑止。
 *   - DCR 由来は client 名に prefix を付け、 運用者が識別・棚卸しできるようにする。
 *
 * 前提: 本番では TRUSTED_HOSTS / TRUSTED_PROXIES を設定すること
 * (Host ヘッダ詐称によるメタデータ汚染・IP 偽装でのレート制限回避を防ぐため)。
 */
class ClientRegistrationController extends AbstractController
{
    /**
     * DCR で作成した client を識別するための名前 prefix (棚卸し用)。
     */
    public const CLIENT_NAME_PREFIX = 'DCR:';

    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly RateLimiterFactory $mcpDcrRegisterLimiter,
        private readonly RateLimiterFactory $mcpDcrRegisterGlobalLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/register', name: 'mcp_oauth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';

        // グローバル上限: IP 偽装 (X-Forwarded-For 回転等) でも無制限に client を作らせない最終バックストップ
        if (!$this->mcpDcrRegisterGlobalLimiter->create('dcr:global')->consume()->isAccepted()) {
            $this->logger->warning('MCP DCR rejected: global rate limit', ['ip' => $ip]);

            return $this->error('rate_limited', 'Too many registration requests', Response::HTTP_TOO_MANY_REQUESTS);
        }
        if (!$this->mcpDcrRegisterLimiter->create('dcr:'.$ip)->consume()->isAccepted()) {
            $this->logger->warning('MCP DCR rejected: per-IP rate limit', ['ip' => $ip]);

            return $this->error('rate_limited', 'Too many registration requests', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->error('invalid_client_metadata', 'Request body must be a JSON object');
        }

        $requestedRedirectUris = $payload['redirect_uris'] ?? null;
        if (!is_array($requestedRedirectUris) || [] === $requestedRedirectUris) {
            return $this->error('invalid_redirect_uri', 'redirect_uris is required');
        }

        $redirectUris = [];
        foreach ($requestedRedirectUris as $uri) {
            if (!is_string($uri) || !$this->isAllowedRedirectUri($uri)) {
                $this->logger->notice('MCP DCR rejected: disallowed redirect_uri', [
                    'ip' => $ip,
                    'redirect_uri' => is_string($uri) ? $uri : '(non-string)',
                ]);

                return $this->error('invalid_redirect_uri', 'redirect_uri not allowed');
            }
            $redirectUris[] = new RedirectUri($uri);
        }

        // client_name は公開 INSERT。 長さ制限＋制御文字除去で保存先汚染・肥大化を防ぐ
        $rawName = is_string($payload['client_name'] ?? null) ? $payload['client_name'] : 'MCP client';
        $clientName = mb_substr((string) preg_replace('/[[:cntrl:]]+/u', '', $rawName), 0, 200);
        if ('' === $clientName) {
            $clientName = 'MCP client';
        }

        $identifier = bin2hex(random_bytes(16));

        // public client (secret なし)。 grant/scope はクライアント要求を無視して MCP read に固定
        $client = new Client(self::CLIENT_NAME_PREFIX.$clientName, $identifier, null);
        $client->setActive(true);
        $client->setRedirectUris(...$redirectUris);
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(...array_map(
            static fn (string $scope): Scope => new Scope($scope),
            McpTokenService::AVAILABLE_SCOPES,
        ));

        try {
            $this->clientManager->save($client);
        } catch (\Throwable $e) {
            // セキュリティ関連の公開 INSERT 失敗を握り潰さず記録し、 raw 500 を漏らさない
            $this->logger->error('MCP DCR client persistence failed', ['ip' => $ip, 'exception' => $e]);

            return $this->error('server_error', 'Failed to register client', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // 死蔵クライアント掃除の grace 判定用に登録時刻を記録する (best-effort)。
        // 記録に失敗しても client 発行は成立させる (記録漏れの client は自動掃除の対象から外れるだけ)。
        try {
            $dcrRecord = new DcrClient();
            $dcrRecord->setClientIdentifier($identifier);
            $dcrRecord->setCreateDate(new \DateTime());
            $this->entityManager->persist($dcrRecord);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            // 記録漏れの client は作成時刻を持たず、 自動掃除 (CleanupDcrClientsCommand) の対象から外れる (リークになる)
            $this->logger->warning('MCP DCR registration record persistence failed; this client will be excluded from automatic cleanup (leak risk)', ['client_id' => $identifier, 'exception' => $e]);
        }

        $this->logger->info('MCP DCR client registered', [
            'client_id' => $identifier,
            'redirect_uris' => array_map(static fn (RedirectUri $u): string => (string) $u, $redirectUris),
            'ip' => $ip,
        ]);

        // RFC 7591 の登録レスポンス (201)
        return new JsonResponse([
            'client_id' => $identifier,
            'client_id_issued_at' => time(),
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'redirect_uris' => array_map(static fn (RedirectUri $u): string => (string) $u, $redirectUris),
            'scope' => implode(' ', McpTokenService::AVAILABLE_SCOPES),
        ], Response::HTTP_CREATED);
    }

    /**
     * redirect_uri は https、 または loopback の http (localhost/127.0.0.1/[::1]) のみ許可する。
     *
     * パーサ差異対策: `\`/`@`/`#` を含む URI は、 PHP parse_url とブラウザ (WHATWG URL) で
     * host 解釈がズレ得る (例: `http://evil.com\@localhost/cb` は PHP では host=localhost だが
     * ブラウザでは evil.com)。 これらは fail-closed で拒否する。
     */
    private function isAllowedRedirectUri(string $uri): bool
    {
        if (str_contains($uri, '\\') || str_contains($uri, '@') || str_contains($uri, '#')) {
            return false;
        }

        $parts = parse_url($uri);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if ('https' === $scheme) {
            return true;
        }

        if ('http' === $scheme) {
            return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        }

        return false;
    }

    private function error(string $error, string $description, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'error_description' => $description], $status);
    }
}
