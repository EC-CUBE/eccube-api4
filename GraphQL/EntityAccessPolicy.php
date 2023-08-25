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

class EntityAccessPolicy
{
    private $allowLists = [];

    private $frontAllowLists = [];

    private SecurityContext $securityContext;

    private Context $requestContext;

    public function __construct(SecurityContext $securityContext, Context $requestContext)
    {
        $this->securityContext = $securityContext;
        $this->requestContext = $requestContext;
    }

    public function canReadEntity(string $entityClass): bool
    {
        if (is_null($this->securityContext->getLoginUser())) {
            return !empty(array_filter($this->frontAllowLists, function (AllowList $al) use ($entityClass) {
                return $al->isAllowed($entityClass);
            }));
        }

        // TODO 管理画面をAPIで実装するまでは管理者画面URL以下でアクセスした場合はすべてのEntityを許可する
        if ($this->requestContext->isAdmin()) {
            return true;
        }

        $role = 'ROLE_OAUTH2_READ:'.strtoupper((new \ReflectionClass($entityClass))->getShortName());

        return $this->securityContext->isGranted($role);
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

    public function addAllowList(AllowList $allowList)
    {
        $this->allowLists[] = $allowList;
    }

    public function addFrontAllowList(AllowList $allowList)
    {
        $this->frontAllowLists[] = $allowList;
    }
}
