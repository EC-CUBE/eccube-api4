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

namespace Plugin\Api\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Plugin\Api\Entity\WebHook;
use Plugin\Api\Repository\WebHookRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class WebHookService implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var WebHookRepository
     */
    private $webHookRepository;

    /**
     * @var WebHookEvents
     */
    private $webHookEvents;

    /**
     * WebHookService constructor.
     * @param RouterInterface $router
     * @param WebHookRepository $webHookRepository
     * @param WebHookEvents $webHookEvents
     */
    public function __construct(RouterInterface $router, WebHookRepository $webHookRepository, WebHookEvents $webHookEvents)
    {
        $this->router = $router;
        $this->webHookRepository = $webHookRepository;
        $this->webHookEvents = $webHookEvents;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'fire',
        ];
    }

    public function fire(FilterResponseEvent $event)
    {
        $events = $this->webHookEvents->toArray();

        if ($events) {
            $client = new Client();
            $pool = new Pool($client, $this->createRequests($events), [
                'concurrency' => 5,
            ]);
            $pool->promise()->wait();
        }
    }

    private function createRequests($events)
    {
        $payload = json_encode($events);
        /** @var WebHook $webHook */
        foreach ($this->webHookRepository->findAll() as $webHook) {
            yield new Request('POST', $webHook->getPayloadUrl(), [
                'Content-Type' => 'application/json',
                'X-ECCUBE-URL' => $this->router->generate('homepage', null, RouterInterface::ABSOLUTE_URL),
            ], $payload);
        }
    }
}
