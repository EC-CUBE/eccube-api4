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

namespace Plugin\Api\GraphQL\Mutation;

use Doctrine\ORM\EntityManager;
use Eccube\Entity\ProductClass;
use Eccube\Repository\ProductClassRepository;
use GraphQL\Type\Definition\Type;
use Plugin\Api\GraphQL\Error\InvalidArgumentException;
use Plugin\Api\GraphQL\Mutation;
use Plugin\Api\GraphQL\Types;

class UpdateProductPriceMutation implements Mutation
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
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(
        Types $types,
        ProductClassRepository $productClassRepository,
        EntityManager $entityManager
    ) {
        $this->types = $types;
        $this->productClassRepository = $productClassRepository;
        $this->entityManager = $entityManager;
    }

    public function getName()
    {
        return 'updateProductPrice';
    }

    public function getMutation()
    {
        return  [
            'type' => $this->types->get(ProductClass::class),
            'args' => [
                'code' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('api.update_product_price.args.description.product_code'),
                ],
                'price01' => [
                    'type' => Type::int(),
                    'description' => trans('api.update_product_price.args.description.price01'),
                ],
                'price02' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => trans('api.update_product_price.args.description.price02'),
                ],
            ],
            'resolve' => [$this, 'updateProductPrice'],
        ];
    }

    public function updateProductPrice($root, $args)
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

        // price01 が指定されている場合は通常価格を更新
        if (array_key_exists('price01', $args)) {
            if ($args['price01'] < 0) {
                throw new InvalidArgumentException('price01: price01 should be 0 or more.;');
            }

            $ProductClass->setPrice01($args['price01']);
        }

        // price02 が指定されている場合は販売価格を更新
        if (array_key_exists('price02', $args)) {
            if ($args['price02'] < 0) {
                throw new InvalidArgumentException('price02: price02 should be 0 or more.;');
            }

            $ProductClass->setPrice02($args['price02']);
        }

        $this->productClassRepository->save($ProductClass);
        $this->entityManager->flush();

        return $ProductClass;
    }
}
