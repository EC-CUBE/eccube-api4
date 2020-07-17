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

namespace Plugin\Api\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Eccube\Entity\Order;
use Eccube\Entity\Product;
use Eccube\Form\Type\Admin\SearchCustomerType;
use Eccube\Form\Type\Admin\SearchOrderType;
use Eccube\Form\Type\Admin\SearchProductType;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\ProductRepository;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Knp\Component\Pager\Paginator;
use Plugin\Api\GraphQL\Types;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;

class ApiController extends AbstractController
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
     * @var KernelInterface
     */
    private $kernel;
    /**
     * @var Paginator
     */
    private $paginator;

    public function __construct(
        Types $types,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        KernelInterface $kernel,
        Paginator $paginator
    ) {
        $this->types = $types;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->kernel = $kernel;
        $this->paginator = $paginator;
    }

    /**
     * @Route("/api", name="api")
     * @Security("has_role('ROLE_OAUTH2_READ')")
     */
    public function index(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        $schema = $this->getSchema();
        $query = $body['query'];
        $variableValues = isset($body['variables']) ? $body['variables'] : null;
        $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);

        if ($this->kernel->isDebug()) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            $result = $result->toArray($debug);
        }

        return $this->json($result);
    }

    /**
     * @return Schema
     */
    private function getSchema()
    {
        return new Schema([
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
        $builder->add('page', TextType::class, [
            'label' => 'api.args.page.description',
            'required' => false,
            'data' => 1,
            'constraints' => [
                new Assert\Regex([
                    'pattern' => "/^\d+$/u",
                    'message' => 'form_error.numeric_only',
                ]),
            ],
        ])->add('limit', TextType::class, [
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
            $type = Type::string();
            if ($formConfig->getOption('multiple')) {
                $type = Type::listOf($type);
            }
            if ($formConfig->getOption('required') && !$formConfig->getOption('multiple')) {
                $type = Type::nonNull($type);
            }
            $acc[$form->getName()] = [
                'type' => $type,
                'defaultValue' => $form->getViewData(),
                'description' => $formConfig->getOption('label') ? trans($formConfig->getOption('label')) : null,
            ];

            return $acc;
        }, []);

        return [
            'type' => Type::listOf($this->types->get($entityClass)),
            'args' => $args,
            'resolve' => function ($root, $args) use ($builder, $resolver) {
                $form = $builder->getForm();
                $form->submit($args);
                if (!$form->isValid()) {
                    $message = 'Invalid error: ';
                    foreach ($form->getErrors(true) as $error) {
                        $message .= sprintf('%s: %s;', $error->getOrigin()->getName(), $error->getMessage());
                    }

                    throw new InvariantViolation($message);
                }
                $data = $form->getData();

                return $this->paginator->paginate($resolver($data), $args['page'], $args['limit']);
            },
        ];
    }
}
