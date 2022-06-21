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

namespace Plugin\Api42\GraphQL\Query;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Query;
use Plugin\Api42\GraphQL\Types;

abstract class SingleResultQuery implements Query
{
    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var Types
     */
    private $types;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * SingleResultQuery constructor.
     */
    public function __construct($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @required
     */
    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Types $types
     * @required
     */
    public function setTypes(Types $types): void
    {
        $this->types = $types;
    }

    public function getQuery()
    {
        return [
            'type' => $this->types->get($this->entityClass),
            'args' => [
                'id' => Type::nonNull(Type::id()),
            ],
            'resolve' => function ($root, $args) {
                return $this->entityManager->getRepository($this->entityClass)->find($args['id']);
            },
        ];

    }
}
