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

namespace Plugin\Api42;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class Event.
 */
class Event implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * OPTIONメソッドの場合は、処理を中断する.
     *
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($request->getMethod() === 'OPTIONS' || $request->getMethod() === 'POST' && ($request->attributes->get('_route') === 'oauth2_token' || $request->attributes->get('_route') === 'oauth2_authorize') || $request->attributes->get('_route') === 'api') {
            $response = $event->getResponse();
            $response->headers->add([
                'Access-Control-Allow-Origin' => '*',
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ]);
        }
    }
}
