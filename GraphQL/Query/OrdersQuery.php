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

namespace Plugin\Api\GraphQL\Query;

use Eccube\Entity\Order;
use Eccube\Form\Type\Admin\SearchOrderType;
use Eccube\Repository\OrderRepository;

class OrdersQuery extends SearchFormQuery
{
    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * OrdersQuery constructor.
     *
     * @param $orderRepository
     */
    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getName()
    {
        return 'orders';
    }

    public function getQuery()
    {
        return $this->createQuery(Order::class, SearchOrderType::class, function ($searchData) {
            return $this->orderRepository->getQueryBuilderBySearchDataForAdmin($searchData);
        });
    }
}
