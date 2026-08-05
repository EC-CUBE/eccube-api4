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
use Plugin\Api44\Entity\DcrClient;

/**
 * @extends AbstractRepository<DcrClient>
 */
class DcrClientRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass = DcrClient::class)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * 指定時刻より前に登録された DCR クライアントを返す (grace を過ぎた掃除候補)。
     *
     * 追跡レコードのある client のみが対象。 機能導入前や記録漏れの client は作成時刻が無く対象外。
     *
     * @return list<DcrClient>
     */
    public function findRegisteredBefore(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.createDate < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('d.createDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
