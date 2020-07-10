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

namespace Plugin\Api\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Plugin\Api\Service\WebHookService;

class EntityListener implements EventSubscriber
{
    /**
     * @var WebHookService
     */
    private $webHookService;

    /**
     * EntityListener constructor.
     * @param WebHookService $webHookService
     */
    public function __construct(WebHookService $webHookService)
    {
        $this->webHookService = $webHookService;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->webHookService->onCreated($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->webHookService->onUpdated($args->getObject());
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $this->webHookService->onDeleted($args->getObject());
    }
}
