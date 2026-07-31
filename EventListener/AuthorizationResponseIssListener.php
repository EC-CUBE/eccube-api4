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

        // response_type=code の成功応答は code を query に持つ。 league はエラー応答を、 登録 redirect_uri に
        // '#' が含まれる場合は fragment に出す。 RFC 9207 は code/error が乗る側に iss を付けるため両方を見る。
        $parts = parse_url($location);
        if (false === $parts) {
            return;
        }
        parse_str($parts['query'] ?? '', $queryParams);
        parse_str($parts['fragment'] ?? '', $fragmentParams);

        // 認可応答 (client への code / error redirect) だけに付与する。 ログイン redirect 等は対象外
        $hasAuthResponse = isset($queryParams['code']) || isset($queryParams['error'])
            || isset($fragmentParams['code']) || isset($fragmentParams['error']);
        if (!$hasAuthResponse) {
            return;
        }

        // issuer は AS メタデータと同一値 (OAuthMetadataBuilder::baseUrl)。 正しさは TRUSTED_HOSTS 設定に依存する (本番必須)
        $issuer = $this->metadata->baseUrl();
        if ('' === $issuer) {
            // baseUrl() は RequestStack が空のとき '' を返す。 空 iss を付けた不正な認可応答を返さないため付与しない
            return;
        }

        // league は iss を出さないため、 応答に既にある iss は redirect_uri 由来 (= client 制御) で信用できない。
        // mix-up 対策の iss は AS が強制するものなので (RFC 9207)、 既存を除去してから AS の issuer で上書きする。
        unset($queryParams['iss'], $fragmentParams['iss']);

        // code/error が乗るコンポーネント (成功=query / fragment エラー=fragment) に iss を載せる。
        if (isset($queryParams['code']) || isset($queryParams['error'])) {
            $queryParams['iss'] = $issuer;
        } else {
            $fragmentParams['iss'] = $issuer;
        }

        $response->headers->set('Location', $this->rebuildUrl($parts, $queryParams, $fragmentParams));
    }

    /**
     * parse_url の各パーツと query / fragment のパラメータから Location URL を組み立て直す。
     *
     * @param array<string, mixed> $parts          parse_url の結果
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $fragmentParams
     */
    private function rebuildUrl(array $parts, array $queryParams, array $fragmentParams): string
    {
        $url = '';
        if (isset($parts['scheme'])) {
            $url .= (string) $parts['scheme'].'://';
        }
        if (isset($parts['user'])) {
            $url .= (string) $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':'.(string) $parts['pass'];
            }
            $url .= '@';
        }
        if (isset($parts['host'])) {
            $url .= (string) $parts['host'];
        }
        if (isset($parts['port'])) {
            $url .= ':'.(string) $parts['port'];
        }
        if (isset($parts['path'])) {
            $url .= (string) $parts['path'];
        }
        if ([] !== $queryParams) {
            $url .= '?'.http_build_query($queryParams);
        }
        if ([] !== $fragmentParams) {
            $url .= '#'.http_build_query($fragmentParams);
        }

        return $url;
    }
}
