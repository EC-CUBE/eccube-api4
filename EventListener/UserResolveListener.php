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

namespace Plugin\Api44\EventListener;

use League\Bundle\OAuth2ServerBundle\Event\UserResolveEvent;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UserResolveListener
{
    /**
     * @var UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface>
     */
    private UserProviderInterface $userProvider;

    /**
     * @var UserPasswordHasherInterface
     */
    private UserPasswordHasherInterface $userPasswordHasher;

    /**
     * @param UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> $userProvider
     * @param UserPasswordHasherInterface $userPasswordHasher
     */
    public function __construct(UserProviderInterface $userProvider, UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userProvider = $userProvider;
        $this->userPasswordHasher = $userPasswordHasher;
    }

    /**
     * @param UserResolveEvent $event
     */
    public function onUserResolve(UserResolveEvent $event): void
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier($event->getUsername());
        } catch (UserNotFoundException $e) {
            return;
        }

        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            return;
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $event->getPassword())) {
            return;
        }

        $event->setUser($user);
    }
}
