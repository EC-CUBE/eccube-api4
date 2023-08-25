<?php

namespace Plugin\Api42\GraphQL;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ScopeUtils
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function canReadEntity($entityClass)
    {
        $roleNames = $this->tokenStorage->getToken()->getRoleNames();
        $role = 'ROLE_OAUTH2_READ:'.strtoupper((new \ReflectionClass($entityClass))->getShortName());
        return in_array($role, $roleNames);
    }
}
