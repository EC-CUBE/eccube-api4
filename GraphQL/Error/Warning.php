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

namespace Plugin\Api42\GraphQL\Error;

use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use GraphQL\Language\Source;

class Warning extends Error implements ClientAware
{
    public function __construct(
        $message = '',
        $nodes = null,
        ?Source $source = null,
        array $positions = [],
        $path = null,
        $previous = null,
        array $extensions = []
    ) {
        $extensions['level'] = Level::Warning;
        parent::__construct($message, $nodes, $source, $positions, $path, $previous, $extensions);
    }

    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return Category::Global;
    }
}
