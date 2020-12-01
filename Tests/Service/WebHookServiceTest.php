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

namespace Plugin\Api\Tests\Service;

use Eccube\Tests\EccubeTestCase;
use Nyholm\Psr7\Request;
use Plugin\Api\Entity\WebHook;
use Plugin\Api\Service\WebHookService;
use ReflectionClass;
use ReflectionException;

class WebHookServiceTest extends EccubeTestCase
{
    /** @var WebHookService */
    private $service;

    public function setUp()
    {
        parent::setUp();

        $this->service = self::$container->get(WebHookService::class);
    }

    public function testCreateRequest_withSecret()
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

    public function testCreateRequest_withoutSecret()
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
     *
     * @param WebHook $WebHook
     * @return Request
     *
     * @throws ReflectionException
     */
    private function invokeCreateRequest($payload, WebHook $WebHook)
    {
        $rc = new ReflectionClass($this->service);
        $method = $rc->getMethod('createRequest');
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, [$payload, $WebHook]);
    }
}
