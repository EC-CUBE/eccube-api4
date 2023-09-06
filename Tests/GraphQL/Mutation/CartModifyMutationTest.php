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
use Plugin\Api42\GraphQL\Mutation\CartModifyMutation;
use Plugin\Api42\GraphQL\Schema;

class CartModifyMutationTest extends EccubeTestCase
{
    private ?CartModifyMutation $mutation;
    private ?Schema $schema;

    /**
     * @var string
     * @lang GraphQL
     */
    private const MUTATION = '
mutation cartModify($input: add_cartInput) {
  cartModify(
    input: $input
  ) {
    cart_key
    CartItems {
      ProductClass {
        id
      }
      quantity
    }
  }
}
';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = self::$container->get(Schema::class);
        // TODO SecurityContextをモックする
    }

    public function testExecuteMutation()
    {
        $faker = $this->getFaker();

        $variables = [
            'input' => [
                'product_class_id' => 11,
                'quantity' => 1,
            ]
        ];

        $result = GraphQL::executeQuery($this->schema,
                              self::MUTATION,
                              null,
                              null,
                              $variables
        );

        $this->assertArrayHasKey('data', $result->toArray());

        $this->assertEquals($variables['input']['product_class_id'], $result->toArray()['data']['cartModify']['CartItems'][0]['ProductClass']['id']);
        $this->assertEquals($variables['input']['quantity'], $result->toArray()['data']['cartModify']['CartItems'][0]['quantity']);
        
    }
}
