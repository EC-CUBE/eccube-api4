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

namespace Plugin\Api\GraphQL;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Customer;
use Eccube\Entity\Order;
use Eccube\Entity\Product;
use Eccube\Form\Type\Admin\SearchCustomerType;
use Eccube\Form\Type\Admin\SearchOrderType;
use Eccube\Form\Type\Admin\SearchProductType;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Util\StringUtil;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Knp\Component\Pager\Paginator;
use Plugin\Api\GraphQL\Error\FormInvalidException;
use Plugin\Api\GraphQL\Type\ConnectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints as Assert;

class Schema extends \GraphQL\Type\Schema
{
    /**
     * @var Types
     */
    private $types;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var Paginator
     */
    private $paginator;

    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;
    /**
     * @var FormFactory
     */
    private $formFactory;

    public function __construct(
        Types $types,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        Paginator $paginator,
        EccubeConfig $eccubeConfig,
        FormFactory $formFactory
    ) {
        $this->types = $types;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->paginator = $paginator;
        $this->eccubeConfig = $eccubeConfig;
        $this->formFactory = $formFactory;

        parent::__construct([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'products' => $this->createQuery(Product::class, SearchProductType::class, function ($searchData) {
                        return $this->productRepository->getQueryBuilderBySearchDataForAdmin($searchData);
                    }),
                    'orders' => $this->createQuery(Order::class, SearchOrderType::class, function ($searchData) {
                        return $this->orderRepository->getQueryBuilderBySearchDataForAdmin($searchData);
                    }),
                    'customers' => $this->createQuery(Customer::class, SearchCustomerType::class, function ($searchData) {
                        return $this->customerRepository->getQueryBuilderBySearchData($searchData);
                    }),
                ],
                'typeLoader' => function ($name) {
                    return $this->types->get($name);
                },
            ]),
        ]);
    }

    private function createQuery($entityClass, $searchFormType, $resolver)
    {
        $builder = $this->formFactory->createBuilder($searchFormType, null, ['csrf_protection' => false]);

        // paging のためのフォームを追加
        $builder->add('page', IntegerType::class, [
            'label' => 'api.args.page.description',
            'required' => false,
            'data' => 1,
            'constraints' => [
                new Assert\Regex([
                    'pattern' => "/^\d+$/u",
                    'message' => 'form_error.numeric_only',
                ]),
            ],
        ])->add('limit', IntegerType::class, [
            'label' => 'api.args.limit.description',
            'required' => false,
            'data' => $this->eccubeConfig->get('eccube_default_page_count'),
            'constraints' => [
                new Assert\Regex([
                    'pattern' => "/^\d+$/u",
                    'message' => 'form_error.numeric_only',
                ]),
            ],
        ]);

        $args = array_reduce($builder->getForm()->all(), function ($acc, $form) {
            /* @var FormInterface $form */
            $formConfig = $form->getConfig();
            $type = $formConfig->getType()->getInnerType() instanceof IntegerType ? Type::int() : Type::string();
            if ($formConfig->getOption('multiple')) {
                $type = Type::listOf($type);
            }
            if ($formConfig->getOption('required') && !$formConfig->getOption('multiple')) {
                $type = Type::nonNull($type);
            }
            $defaultValue = $form->getViewData();
            $acc[$form->getName()] = [
                'type' => $type,
                'defaultValue' => StringUtil::isNotBlank($defaultValue) ? $defaultValue : null,
                'description' => $formConfig->getOption('label') ? trans($formConfig->getOption('label')) : null,
            ];

            return $acc;
        }, []);

        return [
            'type' => new ConnectionType($entityClass, $this->types),
            'args' => $args,
            'resolve' => function ($root, $args) use ($builder, $resolver) {
                $form = $builder->getForm();
                $form->submit($args);

                if (!$form->isValid()) {
                    $message = '';
                    foreach ($form->getErrors(true) as $error) {
                        $message .= sprintf('%s: %s;', $error->getOrigin()->getName(), $error->getMessage());
                    }

                    throw new FormInvalidException($message);
                }

                $data = $form->getData();

                return $this->paginator->paginate($resolver($data), $args['page'], $args['limit']);
            },
        ];
    }
}
