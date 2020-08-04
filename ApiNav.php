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

namespace Plugin\Api;

use Eccube\Common\EccubeNav;

class ApiNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        $menu = [
            'setting' => [
                'children' => [
                    'api' => [
                        'name' => 'api.admin.management',
                        'children' => [
                            'oauth' => [
                                'name' => 'api.admin.oauth.management',
                                'url' => 'admin_api_oauth',
                            ],
                            'webhook' => [
                                'name' => 'api.admin.webhook.management',
                                'url' => 'admin_api_webhook',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if ('dev' === env('APP_ENV')) {
            $menu['setting']['children']['api']['children']['graphiql'] = [
                'name' => 'api.admin.graphiql.name',
                'url' => 'admin_api_graphiql',
            ];
        }

        return $menu;
    }
}
