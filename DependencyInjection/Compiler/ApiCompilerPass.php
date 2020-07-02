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

namespace Plugin\Api\DependencyInjection\Compiler;

use League\OAuth2\Server\CryptKey;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $oauthConfig = $container->getExtensionConfig('trikoder_oauth2');
        $oauthConfig = $container->resolveEnvPlaceholders($oauthConfig, true);
        $privateKey = str_replace('%%kernel.project_dir%%', $projectDir, $oauthConfig[0]['authorization_server']['private_key']);
        $publicKey = str_replace('%%kernel.project_dir%%', $projectDir, $oauthConfig[0]['resource_server']['public_key']);

        if (!$this->isRSAKeyContent($privateKey) && !file_exists($privateKey)
            && !$this->isRSAKeyContent($publicKey) && !file_exists($publicKey)) {
            $this->generateKeys($privateKey, $publicKey);
        }
    }

    private function isRSAKeyContent($string)
    {
        return preg_match(CryptKey::RSA_KEY_PATTERN, $string);
    }

    private function generateKeys($privateKeyPath, $publicKeyPath)
    {
        $res = openssl_pkey_new([
            'digest_alg' => 'sha512',
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($res, $privateKey);

        $publicKey = openssl_pkey_get_details($res)['key'];

        foreach ([$privateKeyPath, $publicKeyPath] as $file) {
            $dir = dirname($file);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);
    }
}
