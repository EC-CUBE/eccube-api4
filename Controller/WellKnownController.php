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

namespace Plugin\Api44\Controller;

use Eccube\Controller\AbstractController;
use Plugin\Api44\Service\OAuthMetadataBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP クライアントが認可方法を自動発見するための公開メタデータ (RFC 9728 / RFC 8414)。
 *
 * 認証不要 (ApiExtension の prepend で `^/\.well-known/oauth-` を security:false にする)。
 * 中身は「認可サーバの場所」の宣言のみで秘密を含まない。
 */
class WellKnownController extends AbstractController
{
    public function __construct(
        private readonly OAuthMetadataBuilder $metadataBuilder,
    ) {
    }

    #[Route(path: '/.well-known/oauth-protected-resource', name: 'mcp_well_known_protected_resource', methods: ['GET'])]
    public function protectedResource(): JsonResponse
    {
        return $this->metadata($this->metadataBuilder->protectedResourceMetadata());
    }

    #[Route(path: '/.well-known/oauth-authorization-server', name: 'mcp_well_known_authorization_server', methods: ['GET'])]
    public function authorizationServer(): JsonResponse
    {
        return $this->metadata($this->metadataBuilder->authorizationServerMetadata());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function metadata(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        // メタデータの URL は実行時の Host から組み立てるため、 共有キャッシュに載せると Host 詐称時に
        // 汚染レスポンスが他者へ配信され得る。 キャッシュ汚染の増幅を断つため no-store にする
        // (本番では加えて TRUSTED_HOSTS の設定が前提)。
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Vary', 'Host');

        return $response;
    }
}
