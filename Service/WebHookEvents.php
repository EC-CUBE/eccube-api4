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

namespace Plugin\Api42\Service;

use Eccube\Entity\Customer;
use Eccube\Entity\Order;
use Eccube\Entity\Product;

class WebHookEvents
{
    private $events = ['created' => [], 'updated' => [], 'deleted' => []];

    /**
     * @var WebHookTrigger[]
     */
    private $triggers = [];

    public function onCreated($entity)
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['created'][] = $this->toEntityDefinition($entity);
            $this->events['created'] = array_unique($this->events['created'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    public function onUpdated($entity)
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['updated'][] = $this->toEntityDefinition($entity);
            $this->events['updated'] = array_unique($this->events['updated'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    public function onDeleted($entity)
    {
        if ($this->isTargetEntity($entity)) {
            $this->events['deleted'][] = $this->toEntityDefinition($entity);
            $this->events['deleted'] = array_unique($this->events['deleted'], SORT_REGULAR);
        } else {
            $this->onAssociationMappingUpdated($entity);
        }
    }

    private function isTargetEntity($entity)
    {
        return $entity instanceof Product
            || $entity instanceof Order
            || $entity instanceof Customer;
    }

    private function toEntityDefinition($entity)
    {
        return [
            'entity' => strtolower((new \ReflectionClass($entity))->getShortName()),
            'id' => $entity->getId(),
        ];
    }

    public function addTrigger(WebHookTrigger $trigger)
    {
        $this->triggers[] = $trigger;
    }

    private function onAssociationMappingUpdated($entity)
    {
        foreach ($this->triggers as $trigger) {
            $target = $trigger->emitFor($entity);
            if ($target) {
                $this->onUpdated($target);
            }
        }
    }

    public function toArray()
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
