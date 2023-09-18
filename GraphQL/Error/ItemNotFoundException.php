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

class ItemNotFoundException extends Danger implements ClientAware
{
    public function __construct($message = '', $nodes = null, $source = null, $positions = [], $path = null, $previous = null, $extensions = [], $entityName = '')
    {
        parent::__construct(message: $message, extensions: array_merge($extensions, ['type' => 'ItemNotFound']));
    }
}
