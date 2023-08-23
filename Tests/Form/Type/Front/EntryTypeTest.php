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

use Eccube\Tests\Form\Type\AbstractTypeTestCase;
use Faker\Generator as Faker;
use Plugin\Api42\Form\Type\Front\EntryType;
use Symfony\Component\Form\FormInterface;

class EntryTypeTest extends AbstractTypeTestCase
{
    /** @var FormInterface */
    protected $form;

    /** @var array デフォルト値（正常系）を設定 */
    protected $formData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF tokenを無効にしてFormを作成
        $this->form = $this->formFactory
            ->createBuilder(EntryType::class, null, [
                'csrf_protection' => false,
            ])
            ->getForm();
        $this->formData = $this->getFormData();
    }

    public function testValidData()
    {
        $this->form->submit($this->formData);
        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidPhoneNumberBlank()
    {
        $this->formData['phone_number'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidName01Blank()
    {
        $this->formData['name']['name01'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidName02Blank()
    {
        $this->formData['name']['name02'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidKana01Blank()
    {
        $this->formData['kana']['kana01'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidKana02Blank()
    {
        $this->formData['kana']['kana02'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidKana01NotKana()
    {
        $this->formData['kana']['kana01'] = 'aaaa';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidKana02NotKana()
    {
        $this->formData['kana']['kana02'] = 'aaaaa';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testValidCompanyNameBlank()
    {
        $this->formData['company_name'] = '';

        $this->form->submit($this->formData);
        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidPostalCodeBlank()
    {
        $this->formData['postal_code'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidPrefBlank()
    {
        $this->formData['address']['pref'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidAddr01Blank()
    {
        $this->formData['address']['addr01'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidAddr02Blank()
    {
        $this->formData['address']['addr02'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidemailBlank()
    {
        $this->formData['email'] = '';
        $this->formData['email'] = '';

        $this->form->submit($this->formData);
        $this->assertFalse($this->form->isValid());
    }

    public function testInvalidPasswordEqualEmail()
    {
        $this->formData['email'] = 'user123@example.com';
        $this->formData['plain_password'] = $this->formData['email'];
        $this->formData['plain_password'] = $this->formData['email'];
        $this->form->submit($this->formData);
        $this->assertEquals(trans('common.password_eq_email'), $this->form->getErrors(true)[0]->getMessage());
    }

    protected function getFormData()
    {
        /** @var Faker $faker */
        $faker = $this->getFaker();

        return $this->formData = [
            'name' => [
                'name01' => $faker->lastName,
                'name02' => $faker->firstName,
            ],
            'kana' => [
                'kana01' => $faker->lastKanaName,
                'kana02' => $faker->firstKanaName,
            ],
            'company_name' => $faker->company,
            'postal_code' => $faker->postcode,
            'address' => [
                'pref' => $faker->numberBetween(1, 47),
                'addr01' => $faker->city,
                'addr02' => $faker->streetName(),
            ],
            'phone_number' => $faker->phoneNumber,
            'email' => $faker->safeEmail(),
            'plain_password' => $faker->password($this->eccubeConfig['eccube_password_min_len'], $this->eccubeConfig['eccube_password_max_len']).'1A',
            'birth' => $faker->dateTimeBetween('-100 years', '-1 days')->format('Y-m-d\TH:i:sP'),
            'sex' => $faker->numberBetween(1, 3),
            'job' => $faker->numberBetween(1, 18),
        ];
    }
}
