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

namespace Plugin\Api42\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use GraphQL\Error\Error;
use Plugin\Api42\GraphQL\EntityAccessPolicy;
use Plugin\Api42\Service\WebHookEvents;

class EntityListener implements EventSubscriber
{
    private WebHookEvents $webHookEvents;

    private EntityAccessPolicy $accessPolicy;

    /**
     * EntityListener constructor.
     */
    public function __construct(WebHookEvents $webHookEvents, EntityAccessPolicy $accessPolicy)
    {
        $this->webHookEvents = $webHookEvents;
        $this->accessPolicy = $accessPolicy;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->validateEntityScope($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->validateEntityScope($args->getObject());
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->webHookEvents->onCreated($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->webHookEvents->onUpdated($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->validateEntityScope($args->getObject());
        $this->webHookEvents->onDeleted($args->getObject());
    }

    private function validateEntityScope(object $entity): void
    {
        $entityClass = get_class($entity);
        if ($this->accessPolicy->canWriteEntity($entityClass) === false) {
            throw new Error("Cannot write entity. `{$entityClass}`");
        }
    }
}
