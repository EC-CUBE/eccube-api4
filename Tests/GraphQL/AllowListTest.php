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

namespace Plugin\Api44\Tests\GraphQL;

use Eccube\Entity\Customer;
use Eccube\Entity\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Api44\GraphQL\AllowList;

class AllowListTest extends TestCase
{
    /**
     * @param $entityClass
     * @param $propertyName
     * @param $expectAllowed
     */
    #[DataProvider('isAllowedWithPropertyNames')]
    public function testIsAllowedWithPropertyNames(string $entityClass, string $propertyName, bool $expectAllowed): void
    {
        $allowList = new AllowList([
            Customer::class => ['id', 'name'],
        ]);

        self::assertEquals($expectAllowed, $allowList->isAllowed($entityClass, $propertyName));
    }

    /**
     * @return list<array{class-string, string, bool}>
     */
    public static function isAllowedWithPropertyNames(): array
    {
        return [
            [Customer::class, 'id', true],
            [Customer::class, 'name', true],
            [Customer::class, 'password', false],
            [Product::class, 'name', false],
        ];
    }
}
