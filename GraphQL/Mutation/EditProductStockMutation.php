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

class EditProductStockMutation implements Mutation
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

    /**
     * @required
     */
    public function setTypes(Types $types): void
    {
        $this->types = $types;
    }

    /**
     * @required
     */
    public function setProductClassRepository(ProductClassRepository $productClassRepository): void
    {
        $this->productClassRepository = $productClassRepository;
    }

    /**
     * @required
     */
    public function setEntityManager(EntityManager $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function getName()
    {
        return 'editProductStock';
    }

    public function getMutation()
    {
        return  [
            'type' => $this->types->get(ProductClass::class),
            'args' => [
                'code' => Type::nonNull(Type::string()),
                'stock' => Type::int(),
                'stock_unlimited' => Type::nonNull(Type::boolean()),
            ],
            'resolve' => [$this, 'editProductStock'],
        ];
    }

    public function editProductStock($root, $args)
    {
        $ProductClasses = $this->productClassRepository->findBy(['code' => $args['code']]);
        if (count($ProductClasses) < 1) {
            throw new InvalidArgumentException('code: No ProductClass found;');
        }
        if (count($ProductClasses) > 1) {
            throw new InvalidArgumentException('code: Multiple ProductClass found;');
        }
        /** @var ProductClass $ProductClass */
        $ProductClass = current($ProductClasses);
        $productStock = $ProductClass->getProductStock();

        $ProductClass->setStock($args['stock']);
        $ProductClass->setStockUnlimited($args['stock_unlimited']);
        $productStock->setStock($args['stock_unlimited'] ? null : $args['stock']);

        $this->productClassRepository->save($ProductClass);
        $this->entityManager->flush();

        return $ProductClass;
    }
}
