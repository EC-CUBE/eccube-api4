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
use Knp\Component\Pager\PaginatorInterface;
use Plugin\Api\GraphQL\Error\InvalidArgumentException;
use Plugin\Api\GraphQL\Query;
use Plugin\Api\GraphQL\Type\ConnectionType;
use Plugin\Api\GraphQL\Types;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints as Assert;

abstract class SearchFormQuery implements Query
{
    /**
     * @var PaginatorInterface
     */
    private $paginator;

    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var Types
     */
    private $types;

    /**
     * @required
     */
    public function setPaginator(PaginatorInterface $paginator): void
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
    public function setFormFactory(FormFactoryInterface $formFactory): void
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
        $this->overrideDateTimeFormat($builder);
        $this->addPagingForms($builder);

        $args = array_reduce($builder->getForm()->all(), function ($acc, $form) {
            /* @var FormInterface $form */
            $formConfig = $form->getConfig();
            $typeClass = get_class($formConfig->getType()->getInnerType());
            switch ($typeClass) {
                case IntegerType::class:
                    $type = Type::int();
                    break;
                case DateTimeType::class:
                    $type = \Plugin\Api\GraphQL\Type\Definition\DateTimeType::dateTime();
                    break;
                default:
                    $type = Type::string();
                    break;
            }
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

                foreach ($form->all() as $field) {
                    $formConfig = $field->getConfig();
                    if ($formConfig->getType()->getInnerType() instanceof DateTimeType) {
                        $value = $args[$field->getName()];
                        if ($value instanceof \DateTime) {
                            $args[$field->getName()] = $value->format(\DateTime::ATOM);
                        }
                    }
                }

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

    /**
     * DateTimeTypeのフォーマットを「yyyy-MM-dd'T'HH:mm:ssZ」に上書きする
     *
     * @param FormBuilderInterface $builder
     */
    private function overrideDateTimeFormat(FormBuilderInterface $builder)
    {
        /** @var FormBuilderInterface $field */
        foreach ($builder->all() as $field) {
            $type = $field->getType()->getInnerType();
            if ($type instanceof DateTimeType) {
                $options = $field->getOptions();
                $options['format'] = "yyyy-MM-dd'T'HH:mm:ssZ";
                $builder->add($field->getName(), get_class($type), $options);
            }
        }
    }

    /**
     * Pagingのためのフォームを追加
     *
     * @param FormBuilderInterface $builder
     */
    private function addPagingForms(FormBuilderInterface $builder)
    {
        $builder->add('page', IntegerType::class, [
            'label' => 'api.search_form_query.args.description.page',
            'required' => false,
            'data' => 1,
            'constraints' => [
                new Assert\Regex([
                    'pattern' => "/^\d+$/u",
                    'message' => 'form_error.numeric_only',
                ]),
                new Assert\GreaterThan(0),
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
                new Assert\GreaterThan(0),
            ],
        ]);
    }
}
