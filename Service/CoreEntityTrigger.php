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

namespace Plugin\Api\Service;

use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductStock;
use Eccube\Entity\ProductTag;
use Eccube\Entity\TaxRule;

class CoreEntityTrigger implements WebHookTrigger
{
    /**
     * @param $entity
     * @return Product|null
     */
    public function emitFor($entity)
    {
        if ($entity instanceof ProductClass) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductCategory) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductTag) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductStock) {
            return $entity->getProductClass()->getProduct();
        } elseif ($entity instanceof TaxRule) {
            return $entity->getProduct();
        }
    }
}
