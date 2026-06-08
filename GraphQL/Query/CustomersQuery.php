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
use Eccube\Entity\Customer;
use Eccube\Form\Type\Admin\SearchCustomerType;
use Eccube\Repository\CustomerRepository;

class CustomersQuery extends SearchFormQuery
{
    /**
     * @var CustomerRepository
     */
    private CustomerRepository $customerRepository;

    /**
     * CustomersQuery constructor.
     *
     * @param CustomerRepository $customerRepository
     */
    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function getName(): string
    {
        return 'customers';
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->createQuery(Customer::class, SearchCustomerType::class, function (array $searchData): QueryBuilder {
            return $this->customerRepository->getQueryBuilderBySearchData($searchData);
        });
    }
}
