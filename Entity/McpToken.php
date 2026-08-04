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

namespace Plugin\Api44\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;
use Plugin\Api44\Repository\McpTokenRepository;

/**
 * 管理画面から発行した MCP 用アクセストークンの表示用メタデータ。
 *
 * 認証・失効の正本は league の AccessToken (oauth2_access_token)。 本エンティティは
 * オーナーが一覧で識別するためのラベル等を保持し、 league トークンの識別子 (jti) を
 * `token_identifier` で参照して失効操作と紐付ける。 JWT 本体は保存しない (発行時のみ表示)。
 */
#[ORM\Table(name: 'plg_api_mcp_token')]
#[ORM\Entity(repositoryClass: McpTokenRepository::class)]
class McpToken extends AbstractEntity
{
    /**
     * @var int|null
     */
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    /**
     * @var string league AccessToken の識別子 (JWT の jti)。 失効操作の連携キー
     */
    #[ORM\Column(name: 'token_identifier', type: 'string', length: 80, unique: true)]
    private string $tokenIdentifier;

    /**
     * @var string オーナーが付ける表示用ラベル
     */
    #[ORM\Column(name: 'label', type: 'string', length: 255)]
    private string $label;

    /**
     * @var list<string> 付与した scope (mcp:*:read)
     */
    #[ORM\Column(name: 'scopes', type: 'simple_array')]
    private array $scopes = [];

    /**
     * @var int 発行した Member の ID
     */
    #[ORM\Column(name: 'member_id', type: 'integer', options: ['unsigned' => true])]
    private int $memberId;

    /**
     * @var \DateTime 有効期限
     */
    #[ORM\Column(name: 'expire_date', type: 'datetimetz')]
    private \DateTime $expireDate;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'create_date', type: 'datetimetz')]
    private \DateTime $createDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenIdentifier(): string
    {
        return $this->tokenIdentifier;
    }

    public function setTokenIdentifier(string $tokenIdentifier): void
    {
        $this->tokenIdentifier = $tokenIdentifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param list<string> $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function setMemberId(int $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function getExpireDate(): \DateTime
    {
        return $this->expireDate;
    }

    public function setExpireDate(\DateTime $expireDate): void
    {
        $this->expireDate = $expireDate;
    }

    public function getCreateDate(): \DateTime
    {
        return $this->createDate;
    }

    public function setCreateDate(\DateTime $createDate): void
    {
        $this->createDate = $createDate;
    }
}
