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

namespace Plugin\Api44\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Plugin\Api44\EventListener\AuthorizationResponseIssListener;
use Plugin\Api44\Service\OAuthMetadataBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * RFC 9207 の iss 付与リスナーの単体テスト。
 *
 * 認可応答に AS の issuer が載ること、 および redirect_uri 由来の (= client 制御の) 既存 iss を
 * AS の issuer で上書きすることを検証する。
 */
final class AuthorizationResponseIssListenerTest extends TestCase
{
    private function dispatch(string $location): string
    {
        $metadata = $this->createMock(OAuthMetadataBuilder::class);
        $metadata->method('baseUrl')->willReturn('https://as.example');
        $listener = new AuthorizationResponseIssListener($metadata);

        $request = new Request();
        $request->attributes->set('_route', 'oauth2_authorize');
        $response = new RedirectResponse($location);
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener->onKernelResponse($event);

        return (string) $response->headers->get('Location');
    }

    public function testAddsIssuerToSuccessResponse(): void
    {
        $location = $this->dispatch('https://client.example/cb?code=abc&state=xyz');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('https://as.example', $params['iss']);
        $this->assertSame('abc', $params['code']);
        $this->assertSame('xyz', $params['state']);
    }

    public function testOverwritesClientControlledIss(): void
    {
        // redirect_uri に仕込まれた iss は client 制御なので、 AS の issuer で上書きされる
        $location = $this->dispatch('https://client.example/cb?iss=https://evil.example&code=abc');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('https://as.example', $params['iss']);
        $this->assertSame('abc', $params['code']);
    }

    public function testIgnoresNonAuthorizationResponse(): void
    {
        // code / error を持たない redirect (ログイン画面等) には iss を付けない
        $location = $this->dispatch('https://client.example/login');

        $this->assertStringNotContainsString('iss=', $location);
    }

    public function testPreservesQueryBearingRedirectUri(): void
    {
        // parse_str/http_build_query で丸ごと再構築すると、 名前の '.'→'_'・空白畳み・重複キー畳み・
        // arr[]→arr[0] でクエリが壊れる。 client が登録した redirect_uri のクエリは原文のまま保ち、
        // iss だけを末尾に足すことを検証する。
        $rawQuery = 'a.b=1&x%20y=2&dup=1&dup=2&arr[]=9&code=CODE';
        $location = $this->dispatch('https://client.example/cb?'.$rawQuery);

        $this->assertSame(
            $rawQuery.'&iss=https%3A%2F%2Fas.example',
            (string) parse_url($location, PHP_URL_QUERY),
        );
    }
}
