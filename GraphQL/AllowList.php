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

class AllowList
{
    private $allows;

    /**
     * AllowList constructor.
     *
     * @param $allows
     */
    public function __construct($allows)
    {
        $this->allows = $allows;
    }

    public function isAllowed($entityName, $propertyName = null)
    {
        $allowProperties = $this->allows[$entityName] ?? [];
        if (is_null($propertyName)) {
            return !empty($allowProperties);
        }
        return in_array($propertyName, $allowProperties, true);
    }

}
