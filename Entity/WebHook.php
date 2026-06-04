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

/**
 * Class WebHook
 */
#[ORM\Table(name: 'plg_api_webhook')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discriminator_type', type: 'string', length: 255)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: \Plugin\Api44\Repository\WebHookRepository::class)]
class WebHook
{
    /**
     * @var int ID
     */
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string Payload URL
     */
    #[ORM\Column(name: 'payload_url', type: 'string', length: 1024)]
    private $payloadUrl;

    /**
     * @var string|null Secret
     */
    #[ORM\Column(name: 'secret', type: 'string', length: 1024, nullable: true)]
    private $secret;

    /**
     * @var bool Whether this WebHook is enabled.
     */
    #[ORM\Column(name: 'enabled', type: 'boolean')]
    private $enabled = false;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'create_date', type: 'datetimetz')]
    private $createDate;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'update_date', type: 'datetimetz')]
    private $updateDate;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
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
    public function setPayloadUrl(string $payloadUrl)
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
    public function setSecret(?string $secret)
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
    public function setEnabled(bool $enabled)
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
    public function setCreateDate(\DateTime $createDate)
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
    public function setUpdateDate(\DateTime $updateDate)
    {
        $this->updateDate = $updateDate;
    }
}
