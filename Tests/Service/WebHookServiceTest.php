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

use Eccube\Tests\EccubeTestCase;
use GuzzleHttp\Psr7\Request;
use Plugin\Api44\Entity\WebHook;
use Plugin\Api44\Service\WebHookService;

class WebHookServiceTest extends EccubeTestCase
{
    /** @var WebHookService */
    private ?WebHookService $service = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = self::getContainer()->get(WebHookService::class);
    }

    public function testCreateRequestWithSecret(): void
    {
        $WebHook = new WebHook();
        $WebHook->setPayloadUrl('http://localhost/hook');
        $WebHook->setSecret('secret');
        $WebHook->setEnabled(true);

        $payload = '[{"entity":"product","id":2,"action":"updated"}]';

        $request = $this->invokeCreateRequest($payload, $WebHook);

        self::assertEquals(
            hash_hmac('sha256', $payload, 'secret'),
            $request->getHeader('X-ECCUBE-Signature')[0]
        );
    }

    public function testCreateRequestWithoutSecret(): void
    {
        $WebHook = new WebHook();
        $WebHook->setPayloadUrl('http://localhost/hook');
        $WebHook->setEnabled(true);

        $payload = '[{"entity":"product","id":2,"action":"updated"}]';

        $request = $this->invokeCreateRequest($payload, $WebHook);

        self::assertFalse($request->hasHeader('X-ECCUBE-Signature'));
    }

    /**
     * @param $payload
     * @param WebHook $WebHook
     *
     * @return Request
     *
     * @throws \ReflectionException
     */
    private function invokeCreateRequest(string $payload, WebHook $WebHook): Request
    {
        $rc = new \ReflectionClass($this->service);
        $method = $rc->getMethod('createRequest');
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, [$payload, $WebHook]);
    }
}
