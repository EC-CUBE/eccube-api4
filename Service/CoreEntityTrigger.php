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

namespace Plugin\Api42\Service;

use Eccube\Entity\Customer;
use Eccube\Entity\CustomerAddress;
use Eccube\Entity\CustomerFavoriteProduct;
use Eccube\Entity\MailHistory;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductStock;
use Eccube\Entity\ProductTag;
use Eccube\Entity\Shipping;
use Eccube\Entity\TaxRule;

class CoreEntityTrigger implements WebHookTrigger
{
    /**
     * @param $entity
     * @return Customer|Order|Product|null
     */
    public function emitFor($entity)
    {
        // Product
        if ($entity instanceof ProductClass) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductCategory) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductTag) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductStock) {
            return is_null($entity->getProductClass()) ? null : $entity->getProductClass()->getProduct();
        } elseif ($entity instanceof TaxRule) {
            return $entity->getProduct();
        } elseif ($entity instanceof ProductImage) {
            return $entity->getProduct();
        }

        // Order
        if ($entity instanceof OrderItem) {
            return $entity->getOrder();
        } elseif ($entity instanceof Shipping) {
            return $entity->getOrder();
        } elseif ($entity instanceof MailHistory) {
            return $entity->getOrder();
        }

        // Customer
        if ($entity instanceof CustomerAddress) {
            return $entity->getCustomer();
        } elseif ($entity instanceof CustomerFavoriteProduct) {
            return $entity->getCustomer();
        }

        return null;
    }
}
