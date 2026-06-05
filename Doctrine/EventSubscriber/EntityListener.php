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

namespace Plugin\Api44\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Plugin\Api44\Service\WebHookEvents;

class EntityListener implements EventSubscriber
{
    /**
     * @var WebHookEvents
     */
    private WebHookEvents $webHookEvents;

    /**
     * EntityListener constructor.
     *
     * @param WebHookEvents $webHookEvents
     */
    public function __construct(WebHookEvents $webHookEvents)
    {
        $this->webHookEvents = $webHookEvents;
    }

    /**
     * @return array<int, string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->webHookEvents->onCreated($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->webHookEvents->onUpdated($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->webHookEvents->onDeleted($args->getObject());
    }
}
