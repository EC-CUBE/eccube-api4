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

namespace Plugin\Api44;

use Eccube\Common\EccubeNav;

class ApiNav implements EccubeNav
{
    /**
     * @return array<string, array<string, array<string, array<string, array<string, array<string, string>>|string>>>>
     */
    public static function getNav(): array
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

        return $menu;
    }
}
