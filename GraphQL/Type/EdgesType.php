<?php


namespace Plugin\Api\GraphQL\Type;


use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Plugin\Api\GraphQL\Types;

class EdgesType extends ObjectType
{
    public function __construct(string $className, Types $types)
    {
        $config = [
            'name' => $className,
            'fields' => [
                'edges' => [
                    'type' => Type::listOf($types->get($className)),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
                'pageInfo' => [
                    'type' => new PageInfoType($className),
                    'resolve' => function ($root) {
                        return $root;
                    },
                ],
            ],
        ];
        parent::__construct($config);
    }
}
