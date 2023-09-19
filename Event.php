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
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
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
            KernelEvents::EXCEPTION => 'onKernelResponse',
        ];
    }

    /**
     * OPTIONメソッドの場合は、処理を中断する.
     *
     * @param ExceptionEvent|ResponseEvent $event
     *
     * @return void
     */
    public function onKernelResponse(ExceptionEvent|ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->getMethod() === 'OPTIONS' || $request->getMethod() === 'POST' && ($request->attributes->get('_route') === 'oauth2_token' || $request->attributes->get('_route') === 'api_logout' || $request->attributes->get('_route') === 'oauth2_authorize') || $request->attributes->get('_route') === 'api') {

            $response = $event->getResponse();
            if ($response === null) {
                return;
            }

            $response->headers->add([
                'Access-Control-Allow-Origin' => '*',
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ]);
        }
    }
}
