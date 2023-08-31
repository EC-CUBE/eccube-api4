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

namespace Plugin\Api42\Service;

use GraphQL\Type\Definition\ObjectType;
use League\Bundle\OAuth2ServerBundle\Manager\ScopeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Scope;
use Plugin\Api42\GraphQL\Types;

class ScopeManager implements ScopeManagerInterface
{
    private Types $types;

    private $scopes;

    public function __construct(Types $types)
    {
        $this->types = $types;

        $allTypes = array_filter($this->types->getAll(), function (ObjectType $type) {
            return !empty($type->getFields());
        });
        asort($allTypes);
        $this->scopes = array_reduce(
            $allTypes,
            function ($acc, $type) {
                $read = 'read:'.$type->name;
                $write = 'write:'.$type->name;
                $acc[$read] = new Scope($read);
                $acc[$write] = new Scope($write);

                return $acc;
            },
            []);
    }

    public function find(string $identifier): ?Scope
    {
        return $this->scopes[$identifier] ?? null;
    }

    public function save(Scope $scope): void
    {
        // NOP
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
