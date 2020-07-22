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

namespace Plugin\Api\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Plugin\Api\Service\WebHookEvents;

class EntityListener implements EventSubscriber
{
    /**
     * @var WebHookEvents
     */
    private $webHookEvents;

    /**
     * EntityListener constructor.
     * @param WebHookEvents $webHookEvents
     */
    public function __construct(WebHookEvents $webHookEvents)
    {
        $this->webHookEvents = $webHookEvents;
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
        $this->webHookEvents->onCreated($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->webHookEvents->onUpdated($args->getObject());
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $this->webHookEvents->onDeleted($args->getObject());
    }
}
