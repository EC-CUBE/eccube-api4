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

class AllowList
{
    /**
     * @var array<string, list<string>>
     */
    private array $allows;

    /**
     * AllowList constructor.
     *
     * @param array<string, list<string>> $allows
     */
    public function __construct(array $allows)
    {
        $this->allows = $allows;
    }

    public function isAllowed(string $entityName, string $propertyName): bool
    {
        $allowProperties = $this->allows[$entityName] ?? [];

        return in_array($propertyName, $allowProperties, true);
    }
}
