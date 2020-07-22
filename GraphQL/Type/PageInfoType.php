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

namespace Plugin\Api\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Knp\Component\Pager\Pagination\PaginationInterface;

class PageInfoType extends ObjectType
{
    public function __construct(string $className)
    {
        $config = [
            'name' => (new \ReflectionClass($className))->getShortName().'PageInfo',
            'fields' => function () {
                return [
                    'hasNextPage' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => function (PaginationInterface $pagination) {
                            return isset($pagination->getPaginationData()['next']);
                        },
                    ],
                    'hasPreviousPage' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => function (PaginationInterface $pagination) {
                            return isset($pagination->getPaginationData()['previous']);
                        },
                    ],
                ];
            },
        ];
        parent::__construct($config);
    }
}
