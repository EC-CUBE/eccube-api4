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

namespace Plugin\Api44\DependencyInjection\Compiler;

use Eccube\Service\Mcp\McpToolScopeMap;
use Plugin\Api44\GraphQL\AllowList;
use Plugin\Api44\GraphQL\Mutation;
use Plugin\Api44\GraphQL\Query;
use Plugin\Api44\GraphQL\Types;
use Plugin\Api44\Service\WebHookEvents;
use Plugin\Api44\Service\WebHookTrigger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;

class ApiCompilerPass implements CompilerPassInterface
{
    /**
     * league/oauth2-server 9 で削除された CryptKey::RSA_KEY_PATTERN 相当のパターン
     */
    private const RSA_KEY_PATTERN =
        '/^(-----BEGIN (RSA )?(PUBLIC|PRIVATE) KEY-----)\R.*(-----END (RSA )?(PUBLIC|PRIVATE) KEY-----)\R?$/s';

    public function process(ContainerBuilder $container): void
    {
        $this->configureTrigger($container);
        $this->configureAllowList($container);
        $this->configureKeyPair($container);
        $this->configureSchema($container);
        $this->configureMcpAccessControl($container);

        $plugins = $container->getParameter('eccube.plugins.enabled');
        if (!in_array('Api44', $plugins)) {
            if ($container->hasDefinition('League\Bundle\OAuth2ServerBundle\EventListener\AddClientDefaultScopesListener')) {
                $def = $container->getDefinition('League\Bundle\OAuth2ServerBundle\EventListener\AddClientDefaultScopesListener');
                $def->clearTags();
            }
        }
    }

    private function configureSchema(ContainerBuilder $container): void
    {
        $queriesServiceDef = $container->getDefinition('api.queries');
        $mutationsServiceDef = $container->getDefinition('api.mutations');
        foreach ($container->getDefinitions() as $definition) {
            if (!$this->isInstantiable($container, $definition->getClass())) {
                continue;
            }
            if (is_subclass_of($definition->getClass(), Query::class)) {
                $queriesServiceDef->addMethodCall('append', [$definition]);
            }
            if (is_subclass_of($definition->getClass(), Mutation::class)) {
                $mutationsServiceDef->addMethodCall('append', [$definition]);
            }
        }
    }

    /**
     * `/admin/mcp` に「最低 1 つの mcp read scope」 を要求する access_control を、 core の
     * `^/<admin> → ROLE_ADMIN` より前 (AccessMap は先頭一致) に構造的に挿入する。
     *
     * config prepend で足すと、 core が prependExtensionConfig(先頭 unshift) で入れる `^/<admin>`
     * ルールに shadow され no-op になる。 そこで SecurityExtension が組んだ security.access_map の
     * add() 呼び出し列の先頭へ直接差し込む。 role は本体 McpToolScopeMap を唯一のソースにする。
     */
    private function configureMcpAccessControl(ContainerBuilder $container): void
    {
        // MCP 本体 (McpToolScopeMap) を持たない ec-cube では保護すべき /admin/mcp ツールが無いので何もしない。
        // Api44 単体を素の本体に載せる構成 (MCP 未搭載) での class-not-found fatal を避ける。
        if (!class_exists(McpToolScopeMap::class) || !$container->hasDefinition('security.access_map')) {
            return;
        }

        $adminRoute = (string) $container->getParameter('eccube_admin_route');
        $matcher = (new Definition(PathRequestMatcher::class, ['^/'.$adminRoute.'/mcp']))->setPublic(false);
        // 本体 McpToolScopeMap と同じ read scope role。 本体を持たない環境 (Api44 単体の phpstan/CI) では
        // MAP の型が解決できず解析が落ちるため直書きする。 領域を増やしたら本体 McpToolScopeMap と同期する。
        $roles = [
            'ROLE_OAUTH2_MCP:PRODUCT:READ',
            'ROLE_OAUTH2_MCP:ORDER:READ',
            'ROLE_OAUTH2_MCP:CUSTOMER:READ',
            'ROLE_OAUTH2_MCP:PLUGIN:READ',
        ];

        $accessMap = $container->getDefinition('security.access_map');
        $calls = $accessMap->getMethodCalls();
        // AccessMap は先頭一致。 core の ^/<admin> → ROLE_ADMIN より前に置くため先頭へ unshift する。
        array_unshift($calls, ['add', [$matcher, $roles, null]]);
        $accessMap->setMethodCalls($calls);
    }

    private function configureTrigger(ContainerBuilder $container): void
    {
        $serviceDef = $container->getDefinition(WebHookEvents::class);
        foreach ($container->getDefinitions() as $definition) {
            if (!$this->isInstantiable($container, $definition->getClass())) {
                continue;
            }
            if (is_subclass_of($definition->getClass(), WebHookTrigger::class)) {
                $serviceDef->addMethodCall('addTrigger', [$definition]);
            }
        }
    }

    /**
     * 抽象クラス等のインスタンス化できないクラスを除外する
     *
     * @param string|null $class
     */
    private function isInstantiable(ContainerBuilder $container, ?string $class): bool
    {
        if (!$class) {
            return false;
        }

        $reflection = $container->getReflectionClass($class, false);

        return $reflection !== null && $reflection->isInstantiable();
    }

    private function configureAllowList(ContainerBuilder $container): void
    {
        $ids = $container->findTaggedServiceIds('eccube.api.allow_list');
        $typesDef = $container->getDefinition(Types::class);
        foreach ($ids as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->setClass(AllowList::class);
            $typesDef->addMethodCall('addAllowList', [new Reference($id)]);
        }
    }

    private function configureKeyPair(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $oauthConfig = $container->getExtensionConfig('league_oauth2_server');
        $oauthConfig = $container->resolveEnvPlaceholders($oauthConfig, true);

        // getExtensionConfig は prepend 順 (先頭=最後に prepend された断片) の配列を返す。 鍵パスを持つ断片が
        // 先頭とは限らない (別の prepend が鍵を含まない断片を先頭に積むことがある) ため、 位置を仮定せず探す。
        $privateKey = null;
        $publicKey = null;
        foreach ($oauthConfig as $fragment) {
            $privateKey ??= $fragment['authorization_server']['private_key'] ?? null;
            $publicKey ??= $fragment['resource_server']['public_key'] ?? null;
        }
        if (null === $privateKey || null === $publicKey) {
            return;
        }
        $privateKey = str_replace('%%kernel.project_dir%%', (string) $projectDir, (string) $privateKey);
        $publicKey = str_replace('%%kernel.project_dir%%', (string) $projectDir, (string) $publicKey);

        if (!$this->isRSAKeyContent($privateKey) && !file_exists($privateKey)
            && !$this->isRSAKeyContent($publicKey) && !file_exists($publicKey)) {
            $this->generateKeys($privateKey, $publicKey);
        }
    }

    private function isRSAKeyContent(string $string): int|false
    {
        return preg_match(self::RSA_KEY_PATTERN, $string);
    }

    private function generateKeys(string $privateKeyPath, string $publicKeyPath): void
    {
        if (false === function_exists('openssl_pkey_new')) {
            throw new \RuntimeException('OpenSSL extension not available');
        }

        $res = openssl_pkey_new([
            'digest_alg' => 'sha512',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($res, $privateKey);

        $publicKey = openssl_pkey_get_details($res)['key'];

        foreach ([$privateKeyPath, $publicKeyPath] as $file) {
            $dir = dirname($file);
            if (!file_exists($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }

        if (false === file_put_contents($privateKeyPath, $privateKey)) {
            throw new \RuntimeException(sprintf('File "%s" was not created', $privateKeyPath));
        }
        chmod($privateKeyPath, 0600);

        if (false === file_put_contents($publicKeyPath, $publicKey)) {
            throw new \RuntimeException(sprintf('File "%s" was not created', $publicKeyPath));
        }
        chmod($publicKeyPath, 0644);
    }
}
