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
use Eccube\Form\Type\Admin\SearchProductType;
use Eccube\Repository\ProductRepository;

class ProductsQuery extends SearchFormQuery
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * ProductQuery constructor.
     *
     * @param $productRepository
     */
    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getName()
    {
        return 'products';
    }

    public function getQuery()
    {
        return $this->createQuery(Product::class, SearchProductType::class, function ($searchData) {
            return $this->productRepository->getQueryBuilderBySearchDataForAdmin($searchData);
        });
    }
}
