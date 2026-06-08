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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneInverseSideMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Plugin\Api44\GraphQL\Type\Definition\DateTimeType;

/**
 * DoctrineのEntityからGraphQLのObjectTypeを変換するクラス.
 */
class Types
{
    /** @var EntityManager */
    private EntityManager $entityManager;

    /**
     * @var array<string, ObjectType>
     */
    private array $types = [];

    /**
     * @var AllowList[]
     */
    private array $allowLists = [];

    /**
     * Types constructor.
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function addAllowList(AllowList $allowList): void
    {
        $this->allowLists[] = $allowList;
    }

    /**
     * Entityに対応するObjectTypeを返す.
     *
     * @param class-string $className Entityクラス名
     */
    public function get(string $className): ObjectType
    {
        if (!isset($this->types[$className])) {
            $this->types[$className] = $this->createObjectType($className);
        }

        return $this->types[$className];
    }

    /**
     * @param class-string $className
     */
    private function createObjectType(string $className): ObjectType
    {
        return new ObjectType([
            'name' => (new \ReflectionClass($className))->getShortName(),
            'fields' => function () use ($className) {
                $classMetadata = $this->entityManager->getClassMetadata($className);
                $fields = array_reduce($classMetadata->fieldMappings, function (array $acc, FieldMapping $mapping) use ($classMetadata): array {
                    $type = $this->convertFieldMappingToType($mapping);
                    $fieldName = $mapping['fieldName'];

                    $allowed = array_filter($this->allowLists, function (AllowList $al) use ($classMetadata, $fieldName): bool {
                        return $al->isAllowed($classMetadata->name, $fieldName);
                    });

                    if ($allowed && $type) {
                        $acc[$fieldName] = $type;
                    }

                    return $acc;
                }, []);

                $fields = array_reduce($classMetadata->associationMappings, function (array $acc, ManyToManyInverseSideMapping|ManyToManyOwningSideMapping|ManyToOneAssociationMapping|OneToManyAssociationMapping|OneToOneInverseSideMapping|OneToOneOwningSideMapping $mapping) use ($classMetadata): array {
                    $fieldName = $mapping['fieldName'];

                    $allowed = array_filter($this->allowLists, function (AllowList $al) use ($classMetadata, $fieldName): bool {
                        return $al->isAllowed($classMetadata->name, $fieldName);
                    });

                    if ($allowed) {
                        $acc[$fieldName] = [
                            'type' => $this->convertAssociationMappingToType($mapping),
                        ];
                    }

                    return $acc;
                }, $fields);

                return $fields;
            },
        ]);
    }

    private function convertFieldMappingToType(FieldMapping $fieldMapping): ScalarType|NonNull|null
    {
        if (isset($fieldMapping['id'])) {
            $type = Type::id();
        } else {
            // マッピングに無い Doctrine 型 (date / bigint / json 等) は null になり得る
            $type = [
                'string' => Type::string(),
                'text' => Type::string(),
                'integer' => Type::int(),
                'decimal' => Type::float(),
                'datetimetz' => DateTimeType::dateTime(),
                'smallint' => Type::int(),
                'boolean' => Type::boolean(),
            ][$fieldMapping['type']] ?? null;
        }

        if ($type === null) {
            return null;
        }

        return $fieldMapping['nullable'] ? $type : Type::nonNull($type);
    }

    /**
     * @return ListOfType<ObjectType>|ObjectType
     */
    private function convertAssociationMappingToType(ManyToManyInverseSideMapping|ManyToManyOwningSideMapping|ManyToOneAssociationMapping|OneToManyAssociationMapping|OneToOneInverseSideMapping|OneToOneOwningSideMapping $mapping): ListOfType|ObjectType
    {
        return $this->isToManyAssociation($mapping) ? Type::listOf($this->get($mapping['targetEntity'])) : $this->get($mapping['targetEntity']);
    }

    private function isToManyAssociation(AssociationMapping $mapping): bool
    {
        return $mapping->isToMany();
    }
}
