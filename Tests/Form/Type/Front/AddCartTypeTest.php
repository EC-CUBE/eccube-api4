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

namespace Plugin\Api42\Tests\Form\Type\Front;

use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Tests\Form\Type\AbstractTypeTestCase;
use Plugin\Api42\Form\Type\Front\AddCartType;
use Symfony\Component\Form\FormInterface;

class AddCartTypeTest extends AbstractTypeTestCase
{
    /** @var FormInterface */
    protected $form;

    /** @var array デフォルト値（正常系）を設定 */
    protected $formData = [];

    protected ?Product $Product;

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF tokenを無効にしてFormを作成
        $this->form = $this->formFactory
            ->createBuilder(AddCartType::class, null, [
                'csrf_protection' => false,
            ])
            ->getForm();
    }

    /**
     * @dataProvider dataProviderCartData
     */
    public function testValidData($product_class_id, $quantity, $expected, $message)
    {
        $this->formData = [
            'product_class_id' => $product_class_id,
            'quantity' => $quantity,
        ];
        $this->form->submit($this->formData);
        $this->assertSame($expected, $this->form->isValid(), print_r((string)$this->form->getErrors(true), true));
        if ($message) {
            $this->assertSame($message, trim((string)$this->form->getErrors(true)));
        }
    }

    public function dataProviderCartData()
    {
        return [
            [11, 1, true, ''],
            [11, 0, false, 'ERROR: 1以上でなければなりません。'],
            [0, 1, false, 'ERROR: 商品が見つかりませんでした。'],
            [1, 1, false, 'ERROR: この商品は現在購入できません。'],
        ];
    }

    public function testHiddenProduct()
    {
        $HiddenStatus = $this->entityManager->find(ProductStatus::class, ProductStatus::DISPLAY_HIDE);
        $this->Product = $this->createProduct('非表示商品');
        $this->Product->setStatus($HiddenStatus);
        $this->entityManager->flush();


        $this->formData = [
            'product_class_id' => $this->Product->getProductClasses()[0]->getId(),
            'quantity' => 1,
        ];
        $this->form->submit($this->formData);

        $this->assertFalse($this->form->isValid(), print_r((string)$this->form->getErrors(true), true));
        $this->assertSame('ERROR: この商品は現在購入できません。', trim((string)$this->form->getErrors(true)));
    }
}
