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
use Plugin\Api42\GraphQL\Types;

class EdgeType extends ObjectType
{
    public function __construct(string $className, Types $types)
    {
        $config = [
            'name' => (new \ReflectionClass($className))->getShortName().'Edge',
            'fields' => [
                'node' => [
                    'type' => $types->get($className),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
            ],
        ];
        parent::__construct($config);
    }
}
