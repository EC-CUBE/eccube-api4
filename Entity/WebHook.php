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
use Plugin\Api44\Repository\WebHookRepository;

/**
 * Class WebHook
 */
#[ORM\Table(name: 'plg_api_webhook')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discriminator_type', type: 'string', length: 255)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: WebHookRepository::class)]
class WebHook extends AbstractEntity
{
    /**
     * @var int|null ID
     */
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    /**
     * @var string Payload URL
     */
    #[ORM\Column(name: 'payload_url', type: 'string', length: 1024)]
    private string $payloadUrl;

    /**
     * @var string|null Secret
     */
    #[ORM\Column(name: 'secret', type: 'string', length: 1024, nullable: true)]
    private ?string $secret = null;

    /**
     * @var bool Whether this WebHook is enabled.
     */
    #[ORM\Column(name: 'enabled', type: 'boolean')]
    private $enabled = false;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'create_date', type: 'datetimetz')]
    private \DateTime $createDate;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'update_date', type: 'datetimetz')]
    private \DateTime $updateDate;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getPayloadUrl(): string
    {
        return $this->payloadUrl;
    }

    /**
     * @param string $payloadUrl
     */
    public function setPayloadUrl(string $payloadUrl): void
    {
        $this->payloadUrl = $payloadUrl;
    }

    /**
     * @return string|null
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * @param string|null $secret
     */
    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return \DateTime
     */
    public function getCreateDate(): \DateTime
    {
        return $this->createDate;
    }

    /**
     * @param \DateTime $createDate
     */
    public function setCreateDate(\DateTime $createDate): void
    {
        $this->createDate = $createDate;
    }

    /**
     * @return \DateTime
     */
    public function getUpdateDate(): \DateTime
    {
        return $this->updateDate;
    }

    /**
     * @param \DateTime $updateDate
     */
    public function setUpdateDate(\DateTime $updateDate): void
    {
        $this->updateDate = $updateDate;
    }
}
