<?php

declare(strict_types=1);

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

namespace Plugin\Api44\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Plugin\Api44\DependencyInjection\ApiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * `ApiExtension::prepend()` の firewall 注入仕様を検証する。
 *
 * - `api` / `mcp` firewall が `admin` の **前**に挿入される (順序が重要: パスマッチで先勝ち)
 * - `mcp` firewall は MCP サーバ (本体同梱) 用で `^/<admin_route>/mcp` を OAuth2 stateless で処理
 * - 既存の admin / customer firewall の `csrf_token_generator` と `anonymous` が削除される
 */
final class ApiExtensionTest extends TestCase
{
    public function testPrependInsertsApiAndMcpFirewallsBeforeAdmin(): void
    {
        $container = $this->makeContainerWithSecurityConfig();

        (new ApiExtension())->prepend($container);

        $firewalls = $this->extractFirewalls($container);
        $names = array_keys($firewalls);

        // 順序保証: dev → api → mcp → admin → customer
        // (mcp は admin の前にないと `^/<admin>/mcp` のリクエストが admin firewall に拾われてしまう)
        $this->assertSame(['dev', 'api', 'mcp', 'admin', 'customer'], $names);
    }

    public function testMcpFirewallShapeMatchesDesign(): void
    {
        $container = $this->makeContainerWithSecurityConfig();
        (new ApiExtension())->prepend($container);

        $mcp = $this->extractFirewalls($container)['mcp'];

        $this->assertSame('^/%eccube_admin_route%/mcp', $mcp['pattern']);
        $this->assertTrue($mcp['security']);
        $this->assertTrue($mcp['stateless']);
        $this->assertTrue($mcp['oauth2']);
        $this->assertSame('member_provider', $mcp['provider']);
    }

    public function testAdminFirewallLosesCsrfTokenAndAnonymous(): void
    {
        $container = $this->makeContainerWithSecurityConfig();
        (new ApiExtension())->prepend($container);

        $admin = $this->extractFirewalls($container)['admin'];

        $this->assertArrayNotHasKey('csrf_token_generator', $admin['form_login']);
        $this->assertArrayNotHasKey('anonymous', $admin);
        // 既存の他の form_login 設定は維持される
        $this->assertSame('admin_login', $admin['form_login']['check_path']);
    }

    public function testCustomerFirewallLosesCsrfTokenAndAnonymous(): void
    {
        $container = $this->makeContainerWithSecurityConfig();
        (new ApiExtension())->prepend($container);

        $customer = $this->extractFirewalls($container)['customer'];

        $this->assertArrayNotHasKey('csrf_token_generator', $customer['form_login']);
        $this->assertArrayNotHasKey('anonymous', $customer);
        $this->assertSame('mypage_login', $customer['form_login']['check_path']);
    }

    private function makeContainerWithSecurityConfig(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('security', [
            'firewalls' => [
                'dev' => ['pattern' => '^/(_(profiler|wdt))/', 'security' => false],
                'admin' => [
                    'pattern' => '^/%eccube_admin_route%/',
                    'provider' => 'member_provider',
                    'form_login' => [
                        'check_path' => 'admin_login',
                        'csrf_token_generator' => 'security.csrf.token_manager',
                    ],
                    'anonymous' => true,
                ],
                'customer' => [
                    'pattern' => '^/',
                    'provider' => 'customer_provider',
                    'form_login' => [
                        'check_path' => 'mypage_login',
                        'csrf_token_generator' => 'security.csrf.token_manager',
                    ],
                    'anonymous' => true,
                ],
            ],
        ]);

        return $container;
    }

    /**
     * `ContainerBuilder::$extensionConfigs` (private) を Reflection で読む。
     * 同一 extension に prepend が複数回あった場合 [0] が最後に prepend された config。
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractFirewalls(ContainerBuilder $container): array
    {
        $refl = new \ReflectionProperty(ContainerBuilder::class, 'extensionConfigs');
        $configs = $refl->getValue($container);
        $this->assertArrayHasKey('security', $configs);

        $firewalls = $configs['security'][0]['firewalls'];
        $this->assertIsArray($firewalls);

        return $firewalls;
    }
}
