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

namespace Plugin\Api44\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Eccube\Repository\AbstractRepository;
use Plugin\Api44\Entity\McpToken;

/**
 * @extends AbstractRepository<McpToken>
 */
class McpTokenRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass = McpToken::class)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * 発行済み MCP トークンを新しい順に返す (一覧表示用)。
     *
     * @return list<McpToken>
     */
    public function findAllOrderByCreateDate(): array
    {
        return $this->findBy([], ['createDate' => 'DESC']);
    }
}
