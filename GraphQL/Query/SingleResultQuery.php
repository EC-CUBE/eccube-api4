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

namespace Plugin\Api44\GraphQL\Query;

use Doctrine\ORM\EntityManagerInterface;
use GraphQL\Type\Definition\Type;
use Plugin\Api44\GraphQL\Query;
use Plugin\Api44\GraphQL\Types;
use Symfony\Contracts\Service\Attribute\Required;

abstract class SingleResultQuery implements Query
{
    /**
     * @var string
     */
    private string $entityClass;

    /**
     * @var Types
     */
    private Types $types;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * SingleResultQuery constructor.
     *
     * @param class-string $entityClass
     */
    public function __construct(string $entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @param EntityManagerInterface $entityManager
     */
    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Types $types
     */
    #[Required]
    public function setTypes(Types $types): void
    {
        $this->types = $types;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return [
            'type' => $this->types->get($this->entityClass),
            'args' => [
                'id' => Type::nonNull(Type::id()),
            ],
            'resolve' => function ($root, array $args): ?object {
                return $this->entityManager->getRepository($this->entityClass)->find($args['id']);
            },
        ];
    }
}
