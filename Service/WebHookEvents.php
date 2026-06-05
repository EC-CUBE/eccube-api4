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

namespace Plugin\Api44\Service;

use Eccube\Entity\Customer;
use Eccube\Entity\Order;
use Eccube\Entity\Product;

class WebHookEvents
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $events = ['created' => [], 'updated' => [], 'deleted' => []];

    /**
     * @var WebHookTrigger[]
     */
    private array $triggers = [];

    public function onCreated(object $entity): void
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['created'][] = $this->toEntityDefinition($entity);
            $this->events['created'] = array_unique($this->events['created'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    public function onUpdated(object $entity): void
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['updated'][] = $this->toEntityDefinition($entity);
            $this->events['updated'] = array_unique($this->events['updated'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    public function onDeleted(object $entity): void
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['deleted'][] = $this->toEntityDefinition($entity);
            $this->events['deleted'] = array_unique($this->events['deleted'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    private function isTargetEntity(object $entity): bool
    {
        return $entity instanceof Product
            || $entity instanceof Order
            || $entity instanceof Customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function toEntityDefinition(object $entity): array
    {
        return [
            'entity' => strtolower((new \ReflectionClass($entity))->getShortName()),
            'id' => $entity->getId(),
        ];
    }

    public function addTrigger(WebHookTrigger $trigger): void
    {
        $this->triggers[] = $trigger;
    }

    private function onAssociationMappingUpdated(object $entity): void
    {
        foreach ($this->triggers as $trigger) {
            $target = $trigger->emitFor($entity);
            if ($target) {
                $this->onUpdated($target);
            }
        }
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $events = [];

        foreach ($this->events['created'] as $def) {
            $def['action'] = 'created';
            $events[] = $def;
        }

        foreach ($this->events['deleted'] as $def) {
            $def['action'] = 'deleted';
            $events[] = $def;
        }

        foreach ($this->events['updated'] as $def) {
            if (!in_array($def, $this->events['created']) && !in_array($def, $this->events['deleted'])) {
                $def['action'] = 'updated';
                $events[] = $def;
            }
        }

        return $events;
    }
}
