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

namespace Plugin\Api44\EventListener;

use Plugin\Api44\Service\OAuthMetadataBuilder;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * RFC 9207: 認可応答 (oauth2_authorize の redirect) に `iss` パラメータを付与する。
 *
 * league/oauth2-server は認可コード応答の生成にフックを持たず iss を出さないため、
 * 認可応答の Location ヘッダに後付けする。 issuer は AS メタデータの issuer と同一値
 * (OAuthMetadataBuilder::baseUrl) を使い、 クライアントの検証と齟齬を出さない。
 *
 * 付与対象は「クライアントへ返す認可応答」(query に code または error を持つ redirect) のみ。
 * 未ログイン時のログイン画面 redirect 等には付与しない。
 */
class AuthorizationResponseIssListener
{
    public function __construct(private readonly OAuthMetadataBuilder $metadata)
    {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if ('oauth2_authorize' !== $event->getRequest()->attributes->get('_route')) {
            return;
        }

        $response = $event->getResponse();
        $location = $response->headers->get('Location');
        if (null === $location) {
            return;
        }

        // 認可応答 (client への code / error redirect) だけに付与する。 ログイン redirect 等は対象外
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        if (!isset($params['code']) && !isset($params['error'])) {
            return;
        }
        if (isset($params['iss'])) {
            return;
        }

        $issuer = $this->metadata->baseUrl();
        if ('' === $issuer) {
            return;
        }

        $separator = str_contains($location, '?') ? '&' : '?';
        $response->headers->set('Location', $location.$separator.'iss='.rawurlencode($issuer));
    }
}
