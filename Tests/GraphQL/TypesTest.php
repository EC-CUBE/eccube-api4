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

use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Eccube\Entity\Member;
use Eccube\Entity\Product;
use Eccube\Tests\EccubeTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugin\Api44\GraphQL\Types;

class TypesTest extends EccubeTestCase
{
    /** @var Types */
    private ?Types $types = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->types = self::getContainer()->get(Types::class);
    }

    #[DataProvider('hideSensitiveFieldsProvider')]
    public function testHideSensitiveFields(string $entityClass, string $field, bool $expectExists): void
    {
        $type = $this->types->get($entityClass);

        self::assertEquals($expectExists, $type->hasField($field));
    }

    /**
     * @return list<array{class-string, string, bool}>
     */
    public static function hideSensitiveFieldsProvider(): array
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
