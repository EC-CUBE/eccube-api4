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

namespace Plugin\Api42\GraphQL;

use ArrayObject;
use GraphQL\Type\Definition\ObjectType;

class Schema extends \GraphQL\Type\Schema
{
    public function __construct(
        Types $types,
        ArrayObject $queries,
        ArrayObject $mutations
    ) {
        parent::__construct([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => array_reduce($queries->getArrayCopy(), function ($acc, Query $query) {
                    $acc[$query->getName()] = $query->getQuery();
                    return $acc;
                }, []),
                'typeLoader' => function ($name) use ($types) {
                    return $types->get($name);
                },
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => array_reduce($mutations->getArrayCopy(), function ($acc, Mutation $mutation) {
                    $acc[$mutation->getName()] = $mutation->getMutation();
                    return $acc;
                }, []),
                'typeLoader' => function ($name) use ($types) {
                    return $types->get($name);
                },
            ]),
        ]);
    }
}
