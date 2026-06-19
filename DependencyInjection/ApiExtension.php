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

namespace Plugin\Api44\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class ApiExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $extensionConfigsRefl = new \ReflectionProperty(ContainerBuilder::class, 'extensionConfigs');
        $extensionConfigsRefl->setAccessible(true);
        $extensionConfigs = $extensionConfigsRefl->getValue($container);

        foreach ($extensionConfigs['security'] as $key => $security) {
            if (isset($security['firewalls'])) {
                $names = array_keys($security['firewalls']);
                $replaced = [];
                foreach ($names as $name) {
                    // adminの前にapi / mcpを追加する
                    if ($name === 'admin') {
                        // OAuth ディスカバリの公開エンドポイント。 認証不要 (メタデータは「場所の宣言」のみで秘密を含まない)。
                        // 実在する 2 つの well-known と /register に**完全一致**で限定し、 他パスを公開化しない
                        // (前方一致だと /.well-known/oauth-anything 等が無意図に公開される)。
                        $replaced['mcp_oauth_public'] = [
                            'pattern' => '^/(\.well-known/oauth-(protected-resource|authorization-server)|register)$',
                            'security' => false,
                        ];
                        $replaced['api'] = [
                            'pattern' => '^/api',
                            'security' => true,
                            'stateless' => true,
                            'oauth2' => true,
                            'provider' => 'member_provider',
                        ];
                        // MCP サーバ (本体同梱) 用の OAuth2 firewall。
                        // ^/<admin_route>/mcp を admin より前に置き、 ステートレスな Bearer 認証で処理する。
                        // 認可は領域別 read scope (mcp:product:read 等) で行い、 本体側 Tool の IsGranted と AND 評価。
                        // 401 は entry_point で RFC 9728 の resource_metadata 付き WWW-Authenticate を返す (自動ディスカバリ)。
                        $replaced['mcp'] = [
                            'pattern' => '^/%eccube_admin_route%/mcp',
                            'security' => true,
                            'stateless' => true,
                            'oauth2' => true,
                            'provider' => 'member_provider',
                            'entry_point' => 'Plugin\Api44\Security\McpAuthenticationEntryPoint',
                        ];
                        unset($security['firewalls']['admin']['form_login']['csrf_token_generator']);
                        unset($security['firewalls']['admin']['anonymous']);
                    }

                    if ($name === 'customer') {
                        unset($security['firewalls']['customer']['form_login']['csrf_token_generator']);
                        unset($security['firewalls']['customer']['anonymous']);
                    }
                    $replaced[$name] = $security['firewalls'][$name];
                }
                $extensionConfigs['security'][$key]['firewalls'] = $replaced;
            }
        }

        $extensionConfigsRefl->setValue($container, $extensionConfigs);
    }

    public function load(array $configs, ContainerBuilder $container)
    {
    }
}
