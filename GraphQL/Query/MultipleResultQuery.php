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
use Eccube\Entity\Cart;
use Eccube\Security\SecurityContext;
use Eccube\Service\CartService;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Query;
use Plugin\Api42\GraphQL\Type\ConnectionType;
use Plugin\Api42\GraphQL\Types;

abstract class MultipleResultQuery implements Query
{
    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @var Types
     */
    private $types;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected $resolver;

    protected SecurityContext $securityContext;
    private CartService $cartService;

    /**
     * SingleResultQuery constructor.
     */
    public function __construct($entityClass, SecurityContext $securityContext, CartService $cartService)
    {
        $this->entityClass = $entityClass;
        $this->securityContext = $securityContext;
        $this->cartService = $cartService;
    }

    /**
     * @param EntityManagerInterface $entityManager
     *
     * @required
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Types $types
     *
     * @required
     */
    public function setTypes(Types $types): void
    {
        $this->types = $types;
    }

    abstract public function getArgs(): ?array;

    abstract public function runResolver($root, $args);

    public function getQuery(): array
    {
        return [
            'type' => Type::listOf($this->types->get($this->entityClass)),
            'args' => $this->getArgs(),
            'resolve' => function ($root, $args) {
                return $this->runResolver($root, $args);
            },
        ];
    }
}

/*public function getQuery()
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

}*/
