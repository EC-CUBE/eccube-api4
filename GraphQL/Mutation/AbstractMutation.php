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

namespace Plugin\Api42\GraphQL\Mutation;
use Eccube\Form\Type\FormTypeWrapper;
use Eccube\Form\Type\MasterType;
use Eccube\Form\Type\Master\SexType;
use Eccube\Form\Type\RepeatedEmailType;
use Eccube\Form\Type\RepeatedPasswordType;
use Eccube\Util\StringUtil;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\Form\Type\IdType;
use Plugin\Api42\GraphQL\Error\FormValidationException;
use Plugin\Api42\GraphQL\Error\Info;
use Plugin\Api42\GraphQL\Error\Warning;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Type\Definition\DateTimeType;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;


abstract class AbstractMutation implements Mutation
{
    private FormFactoryInterface $formFactory;

    private Types $types;

    /** @var Error[] */
    private array $warnings = [];

    /** @var Error[] */
    private array $infos = [];

    abstract public function getName(): string;

    abstract public function getArgsType(): string;

    abstract public function getTypesClass(): string;

    abstract protected function executeMutation($root, array $args): mixed;

    protected function getInputObject(FormInterface $form): InputObjectType
    {
        $fields = [];
        $this->convertArgs($form, $fields);
        return new InputObjectType([
            'name' => $form->getName().'Input',
            'fields' => $fields,
        ]);
    }

    protected function getParentProperty(&$prop, FormInterface $form)
    {
        if ($form->getParent() && !$form->getParent()->isRoot()) {
            $prop = $form->getParent()->getName().'_'.$prop;
            $this->getParentProperty($prop, $form->getParent());
        }
    }

    protected function convertArgs(FormInterface $form, array &$args = []): void
    {
        $formConfig = $form->getConfig();
        $innerTypes = [
            BirthdayType::class,
            SexType::class,
            RepeatedEmailType::class,
            RepeatedPasswordType::class,
            IntegerType::class,
            IdType::class,
        ];
        $innerType = $formConfig->getType()->getInnerType();

        if ($innerType instanceof FormTypeWrapper) {
            // XXX FormTypeWrapper::type is private
            $refClass = new \ReflectionObject($innerType);
            $refProp = $refClass->getProperty('type');
            $refProp->setAccessible(true);
            $innerType = $refProp->getValue($innerType);
        }
        $typeClass = get_class($innerType);

        if (in_array($typeClass, $innerTypes) || count($form) === 0) {
            $type = Type::string();
            if ($innerType->getParent() === MasterType::class) {
                $type = Type::Id();
            }
            switch ($typeClass) {
                case IdType::class:
                    $type = Type::id();
                    break;
                case IntegerType::class:
                    $type = Type::int();
                    break;
                case BirthdayType::class:
                    $type = DateTimeType::dateTime();
                    break;
                case RepeatedEmailType::class:
                case RepeatedPasswordType::class:
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
            $prop = '';
            $this->getParentProperty($prop, $form);
            $args[$prop.$form->getName()] = [
                'type' => $type,
                'defaultValue' => StringUtil::isNotBlank($defaultValue) ? $defaultValue : null,
                'description' => $formConfig->getOption('label') ? trans($formConfig->getOption('label')) : null,
            ];
        } else {
            foreach ($form as $child) {
                $this->convertArgs($child, $args);
            }
        }
    }

    public function getMutation(): array
    {
        $builder = $this->formFactory->createBuilder($this->getArgsType(), null, ['csrf_protection' => false]);
        $form = $builder->getForm();

        return [
            'type' => $this->types->get($this->getTypesClass()),
            'args' => [
                'input' => [
                    'type' => $this->getInputObject($form),
                ],
            ],
            'resolve' => function ($value, array $args, $context, ResolveInfo $info) use ($form) {
                return $this->resolver($value, $args, $context, $info, $form);
            }
        ];
    }

    protected  function convertFormValues(FormInterface $form, &$formValues = [], $input = []): void
    {
        $innerTypes = [
            SexType::class,
            RepeatedEmailType::class,
            RepeatedPasswordType::class,
            BirthdayType::class,
        ];
        $innerType = $form->getConfig()->getType()->getInnerType();
        $typeClass = get_class($innerType);

        if ($innerType instanceof FormTypeWrapper) {
            // XXX FormTypeWrapper::type is private
            $refClass = new \ReflectionObject($innerType);
            $refProp = $refClass->getProperty('type');
            $refProp->setAccessible(true);
            $typeClass = get_class((object)$refProp->getValue($innerType));
        }

        if (in_array($typeClass, $innerTypes) || count($form) === 0) {
            $prop = '';
            $this->getParentProperty($prop, $form);
            if (array_key_exists($prop.$form->getName(), $input) && $input[$prop.$form->getName()] !== null) {
                switch ($typeClass) {
                    case BirthdayType::class:
                        $formValues = $input[$prop.$form->getName()]->format('Y-m-d\TH:i:sP');
                        break;

                    case RepeatedEmailType::class:
                    case RepeatedPasswordType::class:
                        $formValues = [
                            'first' => $input[$prop.$form->getName()],
                            'second' => $input[$prop.$form->getName()],
                        ];
                        break;
                    default:
                        $formValues = $input[$prop.$form->getName()];
                }
            }
        } else {
            foreach ($form as $child) {
                $this->convertFormValues($child, $formValues[$child->getName()], $input);
            }
        }
    }

    protected function resolver($value, array $args, $context, ResolveInfo $info, FormInterface $form): mixed
    {
        $formValues = [];
        $this->convertFormValues($form, $formValues, $args['input']);

        $form->submit($formValues);
        if (!$form->isValid()) {
            $message = '';
            $extensions = [];
            foreach ($form->getErrors(true) as $error) {
                $extensions['errorDetails'][] = [
                    'field' => $error->getOrigin()->getName(),
                    'message' => $error->getMessage(),
                ];
            }

            throw new FormValidationException('Form validation failed', null, null, [], null, null, $extensions);
        }

        $result = $this->executeMutation($value, $form->getData());
        $context['warnings'] = $this->getWarnings();
        $context['infos'] = $this->getInfos();

        return $result;
    }

    public function setTypes(Types $types): self
    {
        $this->types = $types;

        return $this;
    }

    public function getTypes(): Types
    {
        return $this->types;
    }

    public function setFormFactory(FormFactoryInterface $formFactory): self
    {
        $this->formFactory = $formFactory;

        return $this;
    }

    public function getFormFactory(): FormFactoryInterface
    {
        return $this->formFactory;
    }

    public function addInfo(string $message): self
    {
        $this->infos[] = new Info($message);

        return $this;
    }

    public function getInfos(): array
    {
        return $this->infos;
    }

    public function addWarning(string $message): self
    {
        $this->warnings[] = new Warning($message);

        return $this;
    }

    /**
     * @return Warning[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
