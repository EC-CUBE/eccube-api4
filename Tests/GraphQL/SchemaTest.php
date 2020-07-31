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

namespace Plugin\Api\Tests\GraphQL;

use Eccube\Tests\EccubeTestCase;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use Plugin\Api\GraphQL\Schema;

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
                                'id' => '1',
                            ],
                        ],
                        [
                            'node' => [
                                'id' => '2',
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
                            'id' => '1',
                        ],
                        [
                            'id' => '2',
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
                            'id' => '1',
                        ],
                        [
                            'id' => '2',
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
                            'id' => '1',
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
                            'id' => '2',
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
     * @dataProvider queryWithDateTimeProvider
     */
    public function testQueryWithDateTime($dateTime, $expectedErrorMessage = null)
    {
        $query = '{ products(create_datetime_start: "'.$dateTime.'") { nodes { id } } }';

        $result = $this->executeQuery($query);

        if ($expectedErrorMessage) {
            self::assertRegExp($expectedErrorMessage, $result['errors'][0]['message']);
        } else {
            self::assertFalse(isset($result['errors']));
        }
    }

    public function queryWithDateTimeProvider()
    {
        return [
            ['2020-07-30T12:57:08+09:00'],
            ['2020-07-30 12:57:08', '/有効な値ではありません。/'],
            ['2020-07-30T12:57:08', '/有効な値ではありません。/'],
            ['2020-07-30', '/有効な値ではありません。/'],
        ];
    }

    private function executeQuery($query, $variables = null, $readonly = false)
    {
        $op = OperationParams::create(['query' => $query, 'variables' => $variables], $readonly);
        $helper = new Helper();
        $config = ServerConfig::create()->setSchema($this->container->get(Schema::class));
        $result = $helper->executeOperation($config, $op);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }
}
