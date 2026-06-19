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
use Plugin\Api44\Repository\DcrClientRepository;

/**
 * 動的クライアント登録 (DCR) で作成した OAuth クライアントの作成時刻を追跡するメタデータ。
 *
 * league の oauth2_client には作成時刻が無いため、 死蔵クライアント掃除の grace 判定
 * (登録直後の in-flight な client を消さない) に使う作成時刻をここで保持する。
 * 認証の正本は league の Client (oauth2_client)。 本エンティティはその識別子を参照するだけ。
 */
#[ORM\Table(name: 'plg_api_dcr_client')]
#[ORM\Entity(repositoryClass: DcrClientRepository::class)]
class DcrClient extends AbstractEntity
{
    /**
     * @var int|null
     */
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    /**
     * @var string league Client の識別子 (oauth2_client.identifier)。 掃除対象の特定に使う
     */
    #[ORM\Column(name: 'client_identifier', type: 'string', length: 32, unique: true)]
    private string $clientIdentifier;

    /**
     * @var \DateTime DCR 登録時刻 (grace 判定の基準)
     */
    #[ORM\Column(name: 'create_date', type: 'datetimetz')]
    private \DateTime $createDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier(string $clientIdentifier): void
    {
        $this->clientIdentifier = $clientIdentifier;
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
