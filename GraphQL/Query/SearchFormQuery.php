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

use Eccube\Common\EccubeConfig;
use Eccube\Util\StringUtil;
use GraphQL\Type\Definition\Type;
use Knp\Component\Pager\Paginator;
use Plugin\Api\GraphQL\Error\InvalidArgumentException;
use Plugin\Api\GraphQL\Query;
use Plugin\Api\GraphQL\Type\ConnectionType;
use Plugin\Api\GraphQL\Types;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints as Assert;

abstract class SearchFormQuery implements Query
{
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

    /**
     * @var Types
     */
    private $types;

    /**
     * @required
     */
    public function setPaginator(Paginator $paginator): void
    {
        $this->paginator = $paginator;
    }

    /**
     * @required
     */
    public function setEccubeConfig(EccubeConfig $eccubeConfig): void
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * @required
     */
    public function setFormFactory(FormFactory $formFactory): void
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @required
     */
    public function setTypes(Types $types): void
    {
        $this->types = $types;
    }

    protected function createQuery($entityClass, $searchFormType, $resolver)
    {
        $builder = $this->formFactory->createBuilder($searchFormType, null, ['csrf_protection' => false]);

        // paging のためのフォームを追加
        $builder->add('page', IntegerType::class, [
            'label' => 'api.search_form_query.args.description.page',
            'required' => false,
            'data' => 1,
            'constraints' => [
                new Assert\Regex([
                    'pattern' => "/^\d+$/u",
                    'message' => 'form_error.numeric_only',
                ]),
            ],
        ])->add('limit', IntegerType::class, [
            'label' => 'api.search_form_query.args.description.limit',
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

                    throw new InvalidArgumentException($message);
                }

                $data = $form->getData();

                return $this->paginator->paginate($resolver($data), $args['page'], $args['limit']);
            },
        ];
    }
}
