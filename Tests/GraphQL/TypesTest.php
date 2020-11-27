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

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Eccube\Entity\Member;
use Eccube\Entity\Product;
use Eccube\Tests\EccubeTestCase;
use Plugin\Api\GraphQL\Types;

class TypesTest extends EccubeTestCase
{
    /** @var Types */
    private $types;

    public function setUp()
    {
        parent::setUp();
        $this->types = self::$container->get(Types::class);
    }

    /**
     * @dataProvider hideSensitiveFieldsProvider
     */
    public function testHideSensitiveFields($entityClass, $field, $expectExists)
    {
        $type = $this->types->get($entityClass);

        self::assertEquals($expectExists, $type->hasField($field));
    }

    public function hideSensitiveFieldsProvider()
    {
        return [
            [Product::class, 'name', true],
            [Product::class, 'Creator', true],
            [Customer::class, 'name01', true],
            [Customer::class, 'password', false],
            [Customer::class, 'reset_key', false],
            [Customer::class, 'salt', false],
            [Customer::class, 'secret_key', false],
            [Member::class, 'name', true],
            [Member::class, 'password', false],
            [Member::class, 'salt', false],
            [BaseInfo::class, 'authentication_key', false],
        ];
    }
}
