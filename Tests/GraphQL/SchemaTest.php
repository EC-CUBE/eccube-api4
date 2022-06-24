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

namespace Plugin\Api42\Tests\GraphQL;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Tests\EccubeTestCase;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use Plugin\Api42\GraphQL\Schema;

class SchemaTest extends EccubeTestCase
{
    public function testQueryProduct()
    {
        $query = '{ product(id:2) { id } }';

        self::assertEquals([
            'data' => [
                'product' => [
                    'id' => '2',
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryProducts()
    {
        $query = '{
          products (page: 1, limit: 2, create_datetime_start: "2018-09-28T10:14:52+00:00") {
            nodes {
              id
            }
          }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        ['id' => '2'],
                        ['id' => '1']
                    ]
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryProducts_withVariables()
    {
        $query = '
        query productsQuery(
          $page: Int,
          $limit: Int,
          $create_datetime_start: DateTime
        ) {
          products (page: $page, limit: $limit, create_datetime_start: $create_datetime_start) {
            nodes {
              id
            }
          }
        }';

        $variables = [
            'page' => 1,
            'limit' => 2,
            'create_datetime_start' => '2018-09-28T10:14:52+00:00'
        ];

        $result = $this->executeQuery($query, json_encode($variables));

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        ['id' => '2'],
                        ['id' => '1']
                    ]
                ],
            ],
        ], $result);
    }

    public function testQueryConnection_withEdges()
    {
        $query = '{
            products {
                edges {
                    node {
                        id
                    }
                }
            }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => '2',
                            ],
                        ],
                        [
                            'node' => [
                                'id' => '1',
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryConnection_withNodes()
    {
        $query = '{
            products {
                nodes {
                    id
                }
            }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryPageInfo()
    {
        $query = '{
            products {
                nodes {
                    id
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
            }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        [
                            'id' => '2',
                        ],
                        [
                            'id' => '1',
                        ],
                    ],
                    'totalCount' => 2,
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'hasPreviousPage' => false,
                    ],
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryPageInfo_firstPage()
    {
        $query = '{
            products(page: 1, limit: 1) {
                nodes {
                    id
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
            }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        [
                            'id' => '2',
                        ],
                    ],
                    'totalCount' => 2,
                    'pageInfo' => [
                        'hasNextPage' => true,
                        'hasPreviousPage' => false,
                    ],
                ],
            ],
        ], $this->executeQuery($query));
    }

    public function testQueryPageInfo_LastPage()
    {
        $query = '{
            products(page: 2, limit: 1) {
                nodes {
                    id
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
            }
        }';

        self::assertEquals([
            'data' => [
                'products' => [
                    'nodes' => [
                        [
                            'id' => '1',
                        ],
                    ],
                    'totalCount' => 2,
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'hasPreviousPage' => true,
                    ],
                ],
            ],
        ], $this->executeQuery($query));
    }

    /**
     * @dataProvider queryWithPaginationProvider
     */
    public function testQueryWithPagination($page, $limit, $expectedErrorMessage = null)
    {
        $query = '{ products(page: '.$page.', limit: '.$limit.') { nodes { id } } }';

        $result = $this->executeQuery($query);

        if ($expectedErrorMessage) {
            self::assertMatchesRegularExpression($expectedErrorMessage, $result['errors'][0]['message']);
        } else {
            self::assertFalse(isset($result['errors']));
        }
    }

    public function queryWithPaginationProvider()
    {
        return [
            ['1', '1'],
            ['0', '1', '/page: 0より大きくなければなりません。;/'],
            ['1', '0', '/limit: 0より大きくなければなりません。;/'],
        ];
    }

    /**
     * @dataProvider queryWithDateTimeProvider
     */
    public function testQueryWithDateTime($dateTime, $expectedErrorMessage = null)
    {
        $query = '{ products(create_datetime_start: "'.$dateTime.'") { nodes { id } } }';

        $result = $this->executeQuery($query);

        if ($expectedErrorMessage) {
            self::assertMatchesRegularExpression($expectedErrorMessage, $result['errors'][0]['message']);
        } else {
            self::assertFalse(isset($result['errors']));
        }
    }

    public function queryWithDateTimeProvider()
    {
        return [
            ['2020-07-30T12:57:08+09:00'],
            ['2020-07-30 12:57:08', '/DateTime parse error/'],
            ['2020-07-30T12:57:08', '/DateTime parse error/'],
            ['2020-07-30', '/DateTime parse error/'],
        ];
    }

    public function testMutationUpdateStock()
    {
        $query = 'mutation UpdateProductStock(
            $code: String!,
            $stock: Int,
            $stock_unlimited: Boolean!
        ){
            updateProductStock (
                code: $code,
                stock: $stock,
                stock_unlimited: $stock_unlimited
            ) {
                code
                stock
                stock_unlimited
            }
        }';

        $variables = [
            'code' => 'sand-01',
            'stock' => 10,
            'stock_unlimited' => false,
        ];

        self::assertEquals([
            'data' => [
                'updateProductStock' => $variables,
            ],
        ], $this->executeQuery($query, $variables));
    }

    public function testMutationUpdateShipped()
    {
        // 出荷可能な受注を作成
        $Customer = $this->createCustomer();
        $Order = $this->createOrder($Customer);
        $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::NEW);
        $Order->setOrderStatus($OrderStatus);
        $this->entityManager->flush();
        $shippingId = $Order->getShippings()[0]->getId();

        $query = "mutation {
            updateShipped (
                id: ${shippingId},
                shipping_date: \"2020-05-18T12:57:08+00:00\"
                shipping_delivery_name: \"テスト配送業者\"
                tracking_number: \"tracking_number0123\"
                note: \"Hello Notes!\"
            ) {
                id
                shipping_delivery_name
                shipping_date
                tracking_number
                note
            }
        }";

        $result = $this->executeQuery($query);

        self::assertEquals([
            'data' => [
                'updateShipped' => [
                    "id" => $shippingId,
                    "shipping_delivery_name" => "テスト配送業者",
                    "shipping_date" => "2020-05-18T12:57:08+00:00",
                    "tracking_number" => "tracking_number0123",
                    "note" => "Hello Notes!"
                ],
            ],
        ], $result);
    }

    public function testMutationUpdateShipped_withVariables()
    {
        // 出荷可能な受注を作成
        $Customer = $this->createCustomer();
        $Order = $this->createOrder($Customer);
        $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::NEW);
        $Order->setOrderStatus($OrderStatus);
        $this->entityManager->flush();
        $shippingId = $Order->getShippings()[0]->getId();

        $query = 'mutation UpdateShipped (
            $id: ID!,
            $shipping_date: DateTime,
            $shipping_delivery_name: String,
            $tracking_number: String,
            $note: String,
        ){
            updateShipped (
                id: $id,
                shipping_date: $shipping_date
                shipping_delivery_name: $shipping_delivery_name
                tracking_number: $tracking_number
                note: $note
            ) {
                id
                shipping_delivery_name
                shipping_date
                tracking_number
                note
            }
        }';

        $variables = [
            'id' => $shippingId,
            'shipping_date' => '2020-05-18T12:57:08+00:00',
            'shipping_delivery_name' => 'テスト配送業者',
            'tracking_number' => 'tracking_number0123',
            'note' => 'Hello Notes!',
        ];

        self::assertEquals([
            'data' => [
                'updateShipped' => $variables,
            ],
        ], $this->executeQuery($query, $variables));
    }

    private function executeQuery($query, $variables = null, $readonly = false)
    {
        $op = OperationParams::create(['query' => $query, 'variables' => $variables], $readonly);
        $helper = new Helper();
        $config = ServerConfig::create()->setSchema(self::$container->get(Schema::class));
        $result = $helper->executeOperation($config, $op);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }
}
