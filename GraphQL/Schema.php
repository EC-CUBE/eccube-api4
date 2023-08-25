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

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use Plugin\Api42\GraphQL\Type\ConnectionType;

class Schema extends \GraphQL\Type\Schema
{
    public function __construct(
        Types $types,
        \ArrayObject $queries,
        \ArrayObject $mutations,
        ScopeUtils $scopeUtils
    ) {
        parent::__construct([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => static fn () => array_reduce($queries->getArrayCopy(), function ($acc, Query $query) use ($scopeUtils) {
                    $q = $query->getQuery();
                    $entityClass = null;
                    switch (get_class($q['type'])) {
                        case ObjectType::class:
                        case ConnectionType::class:
                            $entityClass = $q['type']->config['entityClass'];
                            break;
                        case ListOfType::class:
                            $entityClass = $q['type']->getOfType()->config['entityClass'];
                            break;
                        default:
                            break;
                    }

                    if ($scopeUtils->canReadEntity($entityClass)) {
                        $acc[$query->getName()] = $q;
                    }

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
