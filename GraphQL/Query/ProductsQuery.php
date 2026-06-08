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

use Doctrine\ORM\QueryBuilder;
use Eccube\Entity\Product;
use Eccube\Form\Type\Admin\SearchProductType;
use Eccube\Repository\ProductRepository;

class ProductsQuery extends SearchFormQuery
{
    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;

    /**
     * ProductQuery constructor.
     *
     * @param $productRepository
     */
    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getName(): string
    {
        return 'products';
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->createQuery(Product::class, SearchProductType::class, function (array $searchData): QueryBuilder {
            return $this->productRepository->getQueryBuilderBySearchDataForAdmin($searchData);
        });
    }
}
