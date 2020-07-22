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

namespace Plugin\Api\GraphQL\Query;

use Eccube\Entity\Product;

class ProductQuery extends SingleResultQuery
{
    /**
     * ProductQuery constructor.
     */
    public function __construct()
    {
        parent::__construct(Product::class);
    }

    public function getName()
    {
        return 'product';
    }
}
