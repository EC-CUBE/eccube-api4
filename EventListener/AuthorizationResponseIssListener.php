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
 * 付与対象は「クライアントへ返す認可応答」(query または fragment に code または error を持つ redirect) のみ。
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
        $rawQuery = $parts['query'] ?? '';
        $rawFragment = $parts['fragment'] ?? '';

        // 判定にのみ parse_str を使う。 parse_str はパラメータ名の '.'・空白を '_' に変換し重複キーを畳むため、
        // redirect_uri 由来のクエリを保つには組み立てに使えない。 iss の除去・追記は生文字列側で行う。
        parse_str($rawQuery, $queryParams);
        parse_str($rawFragment, $fragmentParams);

        // 認可応答 (client への code / error redirect) だけに付与する。 ログイン redirect 等は対象外
        $codeInQuery = isset($queryParams['code']) || isset($queryParams['error']);
        $codeInFragment = isset($fragmentParams['code']) || isset($fragmentParams['error']);
        if (!$codeInQuery && !$codeInFragment) {
            return;
        }

        // issuer は AS メタデータと同一値 (OAuthMetadataBuilder::baseUrl)。 正しさは TRUSTED_HOSTS 設定に依存する (本番必須)
        $issuer = $this->metadata->baseUrl();
        if ('' === $issuer) {
            // baseUrl() は RequestStack が空のとき '' を返す。 空 iss を付けた不正な認可応答を返さないため付与しない
            return;
        }

        // league は iss を出さないため、 応答に既にある iss は redirect_uri 由来 (= client 制御) で信用できない。
        // mix-up 対策の iss は AS が強制する (RFC 9207) ため、 code/error が乗るコンポーネントの生文字列から
        // 既存 iss を除去し、 AS の issuer を追記する。 他のパラメータは生文字列のまま保つ。
        if ($codeInQuery) {
            $rawQuery = $this->appendParam($this->stripParam($rawQuery, 'iss'), 'iss', $issuer);
        } else {
            $rawFragment = $this->appendParam($this->stripParam($rawFragment, 'iss'), 'iss', $issuer);
        }

        $response->headers->set('Location', $this->rebuildUrl($parts, $rawQuery, $rawFragment));
    }

    /**
     * parse_url の各パーツと query / fragment の生文字列から Location URL を組み立て直す。
     * query / fragment は元の文字列をそのまま連結し、 redirect_uri 由来のパラメータを書き換えない。
     *
     * @param array<string, mixed> $parts parse_url の結果
     */
    private function rebuildUrl(array $parts, string $query, string $fragment): string
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
        if ('' !== $query) {
            $url .= '?'.$query;
        }
        if ('' !== $fragment) {
            $url .= '#'.$fragment;
        }

        return $url;
    }

    /**
     * query / fragment の生文字列から、 指定名のパラメータを除去する。
     * 名前比較のためキー部のみ rawurldecode する (値・他パラメータは触らない)。
     */
    private function stripParam(string $raw, string $name): string
    {
        if ('' === $raw) {
            return '';
        }
        $kept = [];
        foreach (explode('&', $raw) as $segment) {
            if ('' === $segment) {
                continue;
            }
            $eq = strpos($segment, '=');
            $key = false === $eq ? $segment : substr($segment, 0, $eq);
            if ($name === rawurldecode($key)) {
                continue;
            }
            $kept[] = $segment;
        }

        return implode('&', $kept);
    }

    /**
     * query / fragment の生文字列に name=rawurlencode(value) を追記する。
     */
    private function appendParam(string $raw, string $name, string $value): string
    {
        $encoded = $name.'='.rawurlencode($value);

        return '' === $raw ? $encoded : $raw.'&'.$encoded;
    }
}
