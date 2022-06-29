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

namespace Plugin\Api42\GraphQL\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\ProductClass;
use Eccube\Repository\ProductClassRepository;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;

class UpdateProductStockMutation implements Mutation
{
    /**
     * @var Types
     */
    private $types;

    /**
     * @var ProductClassRepository
     */
    private $productClassRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        Types $types,
        ProductClassRepository $productClassRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->types = $types;
        $this->productClassRepository = $productClassRepository;
        $this->entityManager = $entityManager;
    }

    public function getName()
    {
        return 'updateProductStock';
    }

    public function getMutation()
    {
        return  [
            'type' => $this->types->get(ProductClass::class),
            'args' => [
                'code' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('api.update_product_stock.args.description.product_code'),
                ],
                'stock' => [
                    'type' => Type::int(),
                    'description' => trans('api.update_product_stock.args.description.stock'),
                ],
                'stock_unlimited' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => trans('api.update_product_stock.args.description.stock_unlimited'),
                ],
            ],
            'resolve' => [$this, 'updateProductStock'],
        ];
    }

    public function updateProductStock($root, $args)
    {
        $ProductClasses = $this->productClassRepository->findBy(['code' => $args['code']]);

        // 更新対象の商品規格をチェック
        if (count($ProductClasses) < 1) {
            throw new InvalidArgumentException('code: No ProductClass found;');
        }
        if (count($ProductClasses) > 1) {
            throw new InvalidArgumentException('code: Multiple ProductClass found;');
        }

        /** @var ProductClass $ProductClass */
        $ProductClass = current($ProductClasses);
        $productStock = $ProductClass->getProductStock();

        if ($args['stock_unlimited']) {
            // 在庫無制限の場合、在庫数の指定があればエラー
            if (array_key_exists('stock', $args)) {
                throw new InvalidArgumentException('stock: Cannot update stock with stock unlimited;');
            }

            // 更新
            $ProductClass->setStockUnlimited(true);
            $ProductClass->setStock(null);
            $productStock->setStock(null);
        } else {
            // 在庫制限がある場合、在庫数の指定がなければエラー
            if (!array_key_exists('stock', $args)) {
                throw new InvalidArgumentException('stock: stock is required when stock limited;');
            }
            if ($args['stock'] < 0) {
                throw new InvalidArgumentException('stock: stock must be a positive integer;');
            }

            // 更新
            $ProductClass->setStockUnlimited(false);
            $ProductClass->setStock($args['stock']);
            $productStock->setStock($args['stock']);
        }

        $this->productClassRepository->save($ProductClass);
        $this->entityManager->flush();

        return $ProductClass;
    }
}
