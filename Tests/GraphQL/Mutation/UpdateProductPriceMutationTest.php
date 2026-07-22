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

namespace Plugin\Api\Tests\GraphQL\Mutation;

use Eccube\Entity\ProductClass;
use Eccube\Repository\ProductClassRepository;
use Eccube\Tests\EccubeTestCase;
use Plugin\Api\GraphQL\Error\InvalidArgumentException;
use Plugin\Api\GraphQL\Mutation\UpdateProductPriceMutation;
use Plugin\Api\GraphQL\Types;

class UpdateProductPriceMutationTest extends EccubeTestCase
{
    /** @var ProductClassRepository */
    private $productClassRepository;

    /** @var UpdateProductPriceMutation */
    private $updateProductPriceMutation;

    public function setUp()
    {
        parent::setUp();
        $types = $this->container->get(Types::class);
        $this->productClassRepository = $this->container->get(ProductClassRepository::class);
        $this->updateProductPriceMutation = new UpdateProductPriceMutation($types, $this->productClassRepository, $this->entityManager);

        // テスト用の商品を作成
        $Product = $this->createProduct();
        /** @var ProductClass[] $ProductClasses */
        $ProductClasses = $Product->getProductClasses();

        $ProductClasses[0]->setCode('produce-code');
        $ProductClasses[0]->setPrice01(2000);
        $ProductClasses[0]->setPrice02(1000);

        $ProductClasses[1]->setCode('produce-code2');
        $ProductClasses[1]->setPrice01(2000);
        $ProductClasses[1]->setPrice02(1000);

        $this->entityManager->persist($Product);
        $this->entityManager->flush();
    }

    /**
     * @dataProvider updateProductPriceProvider
     *
     * @param $args
     * @param $expectPrice01
     * @param $expectPrice02
     * @param $expectExeption
     */
    public function testUpdateProductPrice($args, $expectPrice01, $expectPrice02, $expectExeption)
    {
        try {
            $ProductClass = $this->updateProductPriceMutation->updateProductPrice(null, $args);

            // レスポンスの確認
            self::assertEquals($expectPrice01, $ProductClass->getPrice01());
            self::assertEquals($expectPrice02, $ProductClass->getPrice02());
        } catch (InvalidArgumentException $e) {
            // エラーの確認
            self::assertRegExp($expectExeption, $e->getMessage());
        }

        // DBの確認
        /** @var ProductClass[] $ProductClasses */
        $ProductClasses = $this->productClassRepository->findBy(['code' => $args['code']]);
        $this->entityManager->refresh($ProductClasses[0]);
        self::assertEquals($expectPrice01, $ProductClasses[0]->getPrice01());
        self::assertEquals($expectPrice02, $ProductClasses[0]->getPrice02());
    }

    public function updateProductPriceProvider()
    {
        return [
            [['code' => 'produce-code', 'price01' => 200, 'price02' => 100], 200, 100, false],
            [['code' => 'produce-code', 'price01' => 200, 'price02' => 0], 200, 0, false],
            [['code' => 'produce-code', 'price01' => 200, 'price02' => -1], 2000, 1000, '/price02 should be 0 or more./'],
            [['code' => 'produce-code', 'price01' => 0, 'price02' => 100], 0, 100, false],
            [['code' => 'produce-code', 'price01' => -1, 'price02' => 100], 2000, 1000, '/price01 should be 0 or more./'],
            [['code' => 'produce-code', 'price01' => null, 'price02' => 100], null, 100, false],
            [['code' => 'produce-code', 'price02' => 100], 2000, 100, false],
        ];
    }

    /**
     * 重複するcodeを指定して更新
     */
    public function testUpdateProductPriceMultiple()
    {
        // プロダクトコードを重複させる
        $ProductClasses = $this->productClassRepository->findBy(['code' => 'produce-code']);
        $ProductClasses[0]->setCode('code-multiple');
        $ProductClasses = $this->productClassRepository->findBy(['code' => 'produce-code2']);
        $ProductClasses[0]->setCode('code-multiple');
        $this->entityManager->flush();

        try {
            $this->updateProductPriceMutation->updateProductPrice(null, ['code' => 'code-multiple']);
            // 通らない
            self::assertTrue(false);
        } catch (InvalidArgumentException $e) {
            self::assertRegExp('/Multiple ProductClass found/', $e->getMessage());
        }
    }

    /**
     * 存在しないcodeを指定して更新
     */
    public function testUpdateProductPriceNoData()
    {
        try {
            $this->updateProductPriceMutation->updateProductPrice(null, ['code' => 'code-multiple', 'price01' => 200, 'price02' => 100]);
            // 通らない
            self::assertTrue(false);
        } catch (InvalidArgumentException $e) {
            self::assertRegExp('/No ProductClass found/', $e->getMessage());
        }
    }
}
