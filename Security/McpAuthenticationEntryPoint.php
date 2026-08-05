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

namespace Plugin\Api44\Security;

use Plugin\Api44\Service\OAuthMetadataBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * `^/<admin>/mcp` firewall の認証エントリポイント。
 *
 * league の OAuth2Authenticator は 401 に plain `WWW-Authenticate: Bearer` しか付けないため、
 * RFC 9728 の `resource_metadata=` ポインタを足してクライアントが認可方法を自動発見できるようにする。
 */
class McpAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly OAuthMetadataBuilder $metadataBuilder,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response(
            $authException?->getMessage() ?? 'Authentication required',
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $this->metadataBuilder->wwwAuthenticate()],
        );
    }
}
