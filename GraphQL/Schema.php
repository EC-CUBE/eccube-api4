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

namespace Plugin\Api44\GraphQL;

use GraphQL\Type\Definition\ObjectType;

class Schema extends \GraphQL\Type\Schema
{
    /**
     * @param \ArrayObject<int, Query> $queries
     * @param \ArrayObject<int, Mutation> $mutations
     */
    public function __construct(
        Types $types,
        \ArrayObject $queries,
        \ArrayObject $mutations,
    ) {
        parent::__construct([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => array_reduce($queries->getArrayCopy(), function (array $acc, Query $query): array {
                    $acc[$query->getName()] = $query->getQuery();

                    return $acc;
                }, []),
                'typeLoader' => function ($name) use ($types): ObjectType {
                    return $types->get($name);
                },
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => array_reduce($mutations->getArrayCopy(), function (array $acc, Mutation $mutation): array {
                    $acc[$mutation->getName()] = $mutation->getMutation();

                    return $acc;
                }, []),
                'typeLoader' => function ($name) use ($types): ObjectType {
                    return $types->get($name);
                },
            ]),
        ]);
    }
}
