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

namespace Plugin\Api42\GraphQL;

use Eccube\Request\Context;
use Eccube\Security\SecurityContext;
use Symfony\Component\HttpFoundation\RequestStack;

class EntityAccessPolicy
{
    private $allowLists = [];

    private $frontAllowLists = [];

    private SecurityContext $securityContext;

    private Context $requestContext;

    private RequestStack $requestStack;

    public function __construct(SecurityContext $securityContext, Context $requestContext, RequestStack $requestStack)
    {
        $this->securityContext = $securityContext;
        $this->requestContext = $requestContext;
        $this->requestStack = $requestStack;
    }

    public function canReadEntity(string $entityClass): bool
    {
        // TODO 管理画面をAPIで実装するまでは管理者画面URL以下でアクセスした場合はすべてのEntityを許可する
        if ($this->requestContext->isAdmin()) {
            return true;
        }

        return $this->canAccessEntity($entityClass, false);
    }

    public function canReadProperty(string $entityClass, $fieldName): bool
    {
        if (!$this->canReadEntity($entityClass)) {
            return false;
        }

        $allowLists = $this->securityContext->isGranted('ROLE_ADMIN') ? $this->allowLists : $this->frontAllowLists;

        $allowed = array_filter($allowLists, function (AllowList $al) use ($entityClass, $fieldName) {
            return $al->isAllowed($entityClass, $fieldName);
        });
        return !empty($allowed);
    }

    public function canWriteEntity(string $entityClass): bool
    {
        if (!$this->isApiRequest()) {
            return true;
        }

        return $this->canAccessEntity($entityClass, true);
    }

    private function canAccessEntity(string $entityClass, bool $write): bool
    {
        if (is_null($this->securityContext->getLoginUser())) {
            return !empty(array_filter($this->frontAllowLists, function (AllowList $al) use ($entityClass) {
                return $al->isAllowed($entityClass);
            }));
        }

        $access = $write ? 'WRITE' : 'READ';
        $role = "ROLE_OAUTH2_{$access}:".strtoupper((new \ReflectionClass($entityClass))->getShortName());

        return $this->securityContext->isGranted($role);
    }
    public function addAllowList(AllowList $allowList): void
    {
        $this->allowLists[] = $allowList;
    }

    public function addFrontAllowList(AllowList $allowList): void
    {
        $this->frontAllowLists[] = $allowList;
    }

    private function isApiRequest(): bool
    {
        $mainRequest = $this->requestStack->getMainRequest();
        return $mainRequest != null && $mainRequest->getPathInfo() === '/api';
    }
}
