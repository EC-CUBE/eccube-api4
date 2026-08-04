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

namespace Plugin\Api44\Tests\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Api44\Service\OAuthMetadataBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * TRUSTED_HOSTS 未設定ガードの単体テスト。
 *
 * Host 由来の metadata を本番で trusted hosts 未設定のまま配信すると Host 偽装で偽ドメインを載せられるため、
 * README の必須化 (散文の約束) に加え、 未設定を警告ログで顕在化させる。 その挙動を縛る。
 */
final class OAuthMetadataBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        // 静的な trusted hosts を既定 (空) に戻し、 後続テストへ漏らさない
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function testWarnsWhenTrustedHostsUnsetInProd(): void
    {
        Request::setTrustedHosts([]);
        $logger = $this->createMock(LoggerInterface::class);
        // 1 リクエストで baseUrl は多数回呼ばれるが警告は 1 度だけ
        $logger->expects($this->once())->method('warning');

        $builder = $this->buildFor('prod', $logger);
        $builder->baseUrl();
        $builder->baseUrl();
    }

    public function testDoesNotWarnInDev(): void
    {
        Request::setTrustedHosts([]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->buildFor('dev', $logger)->baseUrl();
    }

    public function testDoesNotWarnWhenTrustedHostsConfiguredInProd(): void
    {
        Request::setTrustedHosts(['^example\.com$']);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->buildFor('prod', $logger)->baseUrl();
    }

    private function buildFor(string $env, LoggerInterface $logger): OAuthMetadataBuilder
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        return new OAuthMetadataBuilder($stack, 'admin', $logger, $env);
    }
}
