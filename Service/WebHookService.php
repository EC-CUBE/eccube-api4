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

use Eccube\Util\StringUtil;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
        if (!$event->isMasterRequest()) {
            return;
        }

        $events = $this->webHookEvents->toArray();

        if ($events) {
            $payload = json_encode($events);

            /** @var WebHook[] $availableWebHooks */
            $availableWebHooks = $this->webHookRepository->findBy(['enabled' => true]);

            $requests = array_map(function (WebHook $WebHook) use ($payload) {
                return $this->createRequest($payload, $WebHook);
            }, $availableWebHooks);

            $client = new Client();
            $pool = new Pool($client, $requests, [
                'concurrency' => 5,
                'options' => [
                    'connect_timeout' => 1,
                    'timeout' => 5,
                    'allow_redirects' => false,
                ],
                'fulfilled' => function (Response $reason, $index) use ($availableWebHooks) {
                    log_info('WebHook request successful.', ['Payload URL' => $availableWebHooks[$index]->getPayloadUrl()]);
                },
                'rejected' => function (RequestException $e, $index) use ($availableWebHooks) {
                    log_error($e->getMessage(), ['Payload URL' => $availableWebHooks[$index]->getPayloadUrl()]);
                },
            ]);
            $p = $pool->promise();
            $p->wait();
        }
    }

    private function createRequest($payload, $WebHook)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-ECCUBE-URL' => $this->router->generate('homepage', [], RouterInterface::ABSOLUTE_URL),
        ];
        if (StringUtil::isNotBlank($WebHook->getSecret())) {
            $headers['X-ECCUBE-Signature'] = hash_hmac('sha256', $payload, $WebHook->getSecret());
        }

        return new Request('POST', $WebHook->getPayloadUrl(), $headers, $payload);
    }
}
