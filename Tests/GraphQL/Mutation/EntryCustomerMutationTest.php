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

namespace Plugin\Api42\Tests\GraphQL\Mutation;

use Eccube\Tests\EccubeTestCase;
use GraphQL\GraphQL;
use Plugin\Api42\GraphQL\Mutation\EntryCustomerMutation;
use Plugin\Api42\GraphQL\Schema;

class EntryCustomerMutationTest extends EccubeTestCase
{
    private ?EntryCustomerMutation $mutation;
    private ?Schema $schema;

    /**
     * @var string
     * @lang GraphQL
     */
    private const MUTATION = '
mutation entryCustomer($input: entryInput) {
  entryCustomer(
    input: $input
  ) {
    id
    name01
    name02
    kana01
    kana02
    company_name
    email
    postal_code
    phone_number
    addr01
    addr02
    Pref {
      id,
      name
    }
    Sex {
      id
      name
    }
    Job {
      id
      name
    }
  }
}';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = self::$container->get(Schema::class);
    }

    public function testExecuteMutation()
    {
        $faker = $this->getFaker();

        $birth = $faker->dateTimeBetween;
        $name01 = $faker->lastName;
        $name02 = $faker->firstName;
        $kana01 = $faker->lastKanaName;
        $kana02 = $faker->firstKanaName;
        $company_name = $faker->company;
        $postal_code = $faker->postcode;
        $pref = 5;
        $addr01 = $faker->city;
        $addr02 = $faker->streetAddress;
        $phone_number = $faker->phoneNumber;
        $email = $faker->safeEmail;
        $password = $faker->lexify('????????????').'a1';
        $sex = 3;
        $job = 1;

        $variables = [
            'input' => [
                'name_name01' => $name01,
                'name_name02' => $name02,
                'kana_kana01' => $kana01,
                'kana_kana02' => $kana02,
                'company_name' => $company_name,
                'postal_code' => $postal_code,
                'address_addr01' => $addr01,
                'address_addr02' => $addr02,
                'address_pref' => $pref,
                'email' => $email,
                'plain_password' => $password,
                'phone_number' => $phone_number,
                'birth' => $birth->format(\DateTime::ATOM),
                'sex' => $sex,
                'job' => $job,
            ]
        ];

        $result = GraphQL::executeQuery($this->schema,
                              self::MUTATION,
                              null,
                              null,
                              $variables
        );

        self::assertArrayHasKey('data', $result->toArray(), array_reduce($result->errors, function ($carry, $item) {
            return $carry.$item->getMessage();
        }, ''));

        self::assertEquals($name01, $result->toArray()['data']['entryCustomer']['name01']);
        self::assertEquals($name02, $result->toArray()['data']['entryCustomer']['name02']);
        self::assertEquals($kana01, $result->toArray()['data']['entryCustomer']['kana01']);
        self::assertEquals($kana02, $result->toArray()['data']['entryCustomer']['kana02']);
        self::assertEquals($company_name, $result->toArray()['data']['entryCustomer']['company_name']);
        self::assertEquals($email, $result->toArray()['data']['entryCustomer']['email']);
        self::assertEquals($postal_code, $result->toArray()['data']['entryCustomer']['postal_code']);
        self::assertEquals(str_replace('-', '', $phone_number), $result->toArray()['data']['entryCustomer']['phone_number']);
        self::assertEquals($addr01, $result->toArray()['data']['entryCustomer']['addr01']);
        self::assertEquals($addr02, $result->toArray()['data']['entryCustomer']['addr02']);
        self::assertEquals($pref, $result->toArray()['data']['entryCustomer']['Pref']['id']);
        self::assertEquals($sex, $result->toArray()['data']['entryCustomer']['Sex']['id']);
        self::assertEquals($job, $result->toArray()['data']['entryCustomer']['Job']['id']);
    }
}
