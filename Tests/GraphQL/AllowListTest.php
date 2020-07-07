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

namespace Plugin\Api\Tests\GraphQL;

use Eccube\Entity\Customer;
use Eccube\Entity\Product;
use PHPUnit\Framework\TestCase;
use Plugin\Api\GraphQL\AllowList;

class AllowListTest extends TestCase
{
    /**
     * @dataProvider isAllowedWithPropertyNames
     * @param $entityClass
     * @param $propertyName
     * @param $expectAllowed
     */
    public function testIsAllowedWithPropertyNames($entityClass, $propertyName, $expectAllowed)
    {
        $allowList = new AllowList([
            Customer::class => ['id', 'name'],
        ]);

        self::assertEquals($expectAllowed, $allowList->isAllowed($entityClass, $propertyName));
    }

    public function isAllowedWithPropertyNames()
    {
        return [
            [Customer::class, 'id', true],
            [Customer::class, 'name', true],
            [Customer::class, 'password', false],
            [Product::class, 'name', false],
        ];
    }
}
