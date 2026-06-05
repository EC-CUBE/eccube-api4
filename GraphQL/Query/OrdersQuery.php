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

namespace Plugin\Api44\GraphQL\Query;

use Doctrine\ORM\QueryBuilder;
use Eccube\Entity\Order;
use Eccube\Form\Type\Admin\SearchOrderType;
use Eccube\Repository\OrderRepository;

class OrdersQuery extends SearchFormQuery
{
    /**
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;

    /**
     * OrdersQuery constructor.
     *
     * @param $orderRepository
     */
    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getName(): string
    {
        return 'orders';
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->createQuery(Order::class, SearchOrderType::class, function (array $searchData): QueryBuilder {
            return $this->orderRepository->getQueryBuilderBySearchDataForAdmin($searchData);
        });
    }
}
