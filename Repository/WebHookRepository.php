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

namespace Plugin\Api\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Eccube\Repository\AbstractRepository;
use Plugin\Api\Entity\WebHook;

class WebHookRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, $entityClass = WebHook::class)
    {
        parent::__construct($registry, $entityClass);
    }
}
