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

namespace Plugin\Api42\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Plugin\Api42\GraphQL\Types;

class ConnectionType extends ObjectType
{
    public function __construct(string $className, Types $types)
    {
        $config = [
            'name' => (new \ReflectionClass($className))->getShortName().'Connection',
            'fields' => [
                'edges' => [
                    'type' => Type::listOf(new EdgeType($className, $types)),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
                'nodes' => [
                    'type' => Type::listOf($types->get($className)),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
                'pageInfo' => [
                    'type' => Type::nonNull(new PageInfoType($className)),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
                'totalCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => function (PaginationInterface $pagination) {
                        return $pagination->getTotalItemCount();
                    },
                ],
            ],
        ];
        parent::__construct($config);
    }
}
