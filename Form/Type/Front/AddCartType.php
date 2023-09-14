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

namespace Plugin\Api42\Form\Type\Front;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\ProductClass;
use Eccube\Form\FormBuilder;
use Eccube\Form\FormError;
use Eccube\Form\FormEvent;
use Eccube\Form\Type\AbstractType;
use Eccube\Repository\ProductClassRepository;
use Eccube\Validator\Constraints as Assert;
use Plugin\Api42\Form\Type\IdType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class AddCartType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $config;

    /**
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    public function __construct(ProductClassRepository $productClassRepository, EccubeConfig $config)
    {
        $this->productClassRepository = $productClassRepository;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('product_class_id', IdType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(['pattern' => '/^\d+$/']),
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'maxlength' => $this->config['eccube_int_len'],
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThanOrEqual([
                        'value' => 1,
                    ]),
                    new Assert\Regex(['pattern' => '/^\d+$/']),
                ],
            ]);

        $builder->onPostSubmit(function (FormEvent $event) {
            /** @var ProductClass $ProductClass */
            $ProductClass = $this->productClassRepository->find($event->getData()['product_class_id']);
            if (null === $ProductClass) {
                $event->getForm()->addError(new FormError('商品が見つかりませんでした。'));
            } else {
                if (!$ProductClass->isVisible()) {
                    $event->getForm()->addError(new FormError('この商品は現在購入できません。'));
                }
                if ($ProductClass->getProduct()->getStatus()->getId() !== ProductStatus::DISPLAY_SHOW) {
                    $event->getForm()->addError(new FormError('この商品は現在購入できません。'));
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'add_cart';
    }
}
