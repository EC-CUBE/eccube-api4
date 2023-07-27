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

namespace Plugin\Api42\EventListener;

use Eccube\Security\Core\User\UserPasswordHasher;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use League\Bundle\OAuth2ServerBundle\Event\UserResolveEvent;

final class UserResolveListener
{
    /**
     * @var CustomerProvider
     */
    private $customerProvider;

    /**
     * @var MemberProvider
     */
    private $memberProvider;

    /**
     * @var UserPasswordHasher
     */
    private $userPasswordEncoder;

    /**
     * @param UserProviderInterface $customerProvider
     * @param UserProviderInterface $memberProvider
     * @param UserPasswordHasher $userPasswordEncoder
     */
    public function __construct(UserProviderInterface $customerProvider, UserProviderInterface $memberProvider, UserPasswordHasher $userPasswordEncoder)
    {
        $this->customerProvider = $customerProvider;
        $this->memberProvider = $memberProvider;
        $this->userPasswordEncoder = $userPasswordEncoder;
    }

    /**
     * @param UserResolveEvent $event
     */
    public function onUserResolve(UserResolveEvent $event): void
    {
        try {
            $user = $this->customerProvider->loadUserByUsername($event->getUsername());
        } catch (UserNotFoundException $e) {
            try {
                $user = $this->memberProvider->loadUserByUsername($event->getUsername());
            } catch (UserNotFoundException $e) {
                return;
            }
        }

        if (!$this->userPasswordEncoder->isPasswordValid($user, $event->getPassword())) {
            return;
        }

        $event->setUser($user);
    }
}
