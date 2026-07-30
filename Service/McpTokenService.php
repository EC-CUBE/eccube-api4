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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Member;
use League\Bundle\OAuth2ServerBundle\Entity\AccessToken as AccessTokenEntity;
use League\Bundle\OAuth2ServerBundle\Entity\Client as ClientEntity;
use League\Bundle\OAuth2ServerBundle\Entity\Scope as ScopeEntity;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken as AccessTokenModel;
use League\Bundle\OAuth2ServerBundle\Model\Client as ClientModel;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\OAuth2\Server\CryptKey;
use Plugin\Api44\Entity\McpToken;

/**
 * 管理画面から MCP 用のアクセストークン (PAT) を発行・失効する。
 *
 * 認証・失効の正本は league の AccessToken (oauth2_access_token)。 本サービスは
 * 専用クライアント (CLIENT_IDENTIFIER) を使い、 操作中の Member に紐づく署名済み JWT を
 * 発行する。 JWT 本体は保存せず発行時のみ返す。 表示用メタは McpToken に保存する。
 */
class McpTokenService
{
    /**
     * PAT 専用の内部クライアント識別子 (PluginManager::enable で生成)。
     */
    public const CLIENT_IDENTIFIER = 'mcp_pat';

    /**
     * 発行を許可する scope。 MCP の領域別 read のみ (write/管理系は発行不可)。
     *
     * @var list<string>
     */
    public const AVAILABLE_SCOPES = [
        'mcp:product:read',
        'mcp:order:read',
        'mcp:customer:read',
        'mcp:plugin:read',
    ];

    /**
     * 発行を許可する有効日数。 フォームのプリセットと一致させ、 フォーム外からの発行も縛る。
     *
     * @var list<int>
     */
    public const AVAILABLE_EXPIRE_DAYS = [30, 90, 180, 365];

    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly AccessTokenManagerInterface $accessTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $privateKeyPath,
    ) {
    }

    /**
     * トークンを発行し、 署名済み JWT を返す (発行時のみ取得可能)。
     *
     * @param list<string> $scopes 要求 scope (mcp:*:read のみ採用)
     * @param int          $expireDays 有効日数 (AVAILABLE_EXPIRE_DAYS のみ)
     */
    public function issue(Member $member, string $label, array $scopes, int $expireDays): string
    {
        // 防御的に mcp:*:read 以外を除外する (フォームの制限が外れても write 等を発行させない)
        $scopes = array_values(array_intersect($scopes, self::AVAILABLE_SCOPES));
        if ([] === $scopes) {
            throw new \InvalidArgumentException('At least one MCP scope is required.');
        }
        // 有効日数も scope と同じくフォーム外からの値を縛る (0=即失効 や 過大な値を防ぐ)
        if (!\in_array($expireDays, self::AVAILABLE_EXPIRE_DAYS, true)) {
            throw new \InvalidArgumentException('Invalid expiration days.');
        }
        // 発行者 (sub / 監査) は必須。 null を 0 に丸めて誤った帰属を残さない
        $memberId = $member->getId();
        if (null === $memberId) {
            throw new \InvalidArgumentException('Member id is required.');
        }

        $client = $this->findOrCreatePatClient();

        $identifier = 'mcp-'.bin2hex(random_bytes(16));
        $expiry = new \DateTimeImmutable(sprintf('+%d days', $expireDays));
        $userIdentifier = $member->getUsername(); // login_id を JWT の sub にする

        // JWT エンティティを組み立てる (副作用なし。 署名は永続化成功後に行う)
        $tokenEntity = new AccessTokenEntity();
        $tokenEntity->setIdentifier($identifier);
        $tokenEntity->setExpiryDateTime($expiry);
        $tokenEntity->setUserIdentifier($userIdentifier);
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier(self::CLIENT_IDENTIFIER);
        $tokenEntity->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $scopeEntity = new ScopeEntity();
            $scopeEntity->setIdentifier($scope);
            $tokenEntity->addScope($scopeEntity);
        }

        // 表示用メタ (ラベル・scope・発行者・期限)
        $mcpToken = new McpToken();
        $mcpToken->setTokenIdentifier($identifier);
        $mcpToken->setLabel($label);
        $mcpToken->setScopes($scopes);
        $mcpToken->setMemberId($memberId);
        $mcpToken->setExpireDate(\DateTime::createFromInterface($expiry));
        $mcpToken->setCreateDate(new \DateTime());

        // 失効判定の正本 (AccessToken model) にも JWT (108-112) と同じ scope を積む。 空にすると
        // oauth2_access_token.scopes が JWT と恒久的に食い違い、 introspection・監査・scope ベースの
        // 掃除など DB レコードを信頼する経路が誤動作する。
        $modelScopes = array_map(static fn (string $scope): Scope => new Scope($scope), $scopes);

        // 失効判定の正本 (AccessToken model) と表示用メタを 1 トランザクションで永続化する。
        // どちらか一方だけ残ると「失効できない生トークン」や「実体のないメタ」が生じるため不可分にする。
        $this->entityManager->wrapInTransaction(function () use ($identifier, $expiry, $client, $userIdentifier, $modelScopes, $mcpToken): void {
            $this->accessTokenManager->save(new AccessTokenModel($identifier, $expiry, $client, $userIdentifier, $modelScopes));
            $this->entityManager->persist($mcpToken);
            $this->entityManager->flush();
        });

        // 永続化が確定してから署名する。 上で失敗した場合は JWT が一切生成されない (生トークンを残さない)
        $tokenEntity->setPrivateKey(new CryptKey($this->privateKeyPath, null, false));

        return $tokenEntity->toString();
    }

    /**
     * PAT 発行用の内部クライアントを取得する。 無ければ冪等生成する。
     *
     * league の doctrine persistence は eccube:plugin:enable の時点では未配線のため、
     * enable 時には生成できない。 実行時 (初回発行時) に生成する。 grant は
     * authorization_code、 scope は MCP の領域別 read に固定する。
     */
    private function findOrCreatePatClient(): ClientModel
    {
        $client = $this->clientManager->find(self::CLIENT_IDENTIFIER);
        if ($client instanceof ClientModel) {
            return $client;
        }

        $client = new ClientModel('MCP PAT', self::CLIENT_IDENTIFIER, null);
        $client->setActive(true);
        $client->setGrants(new Grant('authorization_code'));
        $client->setScopes(...array_map(
            static fn (string $scope): Scope => new Scope($scope),
            self::AVAILABLE_SCOPES,
        ));
        $this->clientManager->save($client);

        return $client;
    }

    /**
     * トークンを失効する。 league token を revoke し (即 401)、 表示用メタを削除する。
     *
     * @return bool 失効対象の league token が見つかり失効できたか。 false の場合は
     *              既に期限切れ削除済み等で revoke 対象が無かったことを示す (呼び出し側で警告する)
     */
    public function revoke(McpToken $mcpToken): bool
    {
        $accessToken = $this->accessTokenManager->find($mcpToken->getTokenIdentifier());
        $found = null !== $accessToken;
        if ($found) {
            $accessToken->revoke();
            $this->accessTokenManager->save($accessToken);
        }

        $this->entityManager->remove($mcpToken);
        $this->entityManager->flush();

        return $found;
    }
}
