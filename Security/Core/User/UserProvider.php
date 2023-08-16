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

namespace Plugin\Api42\Security\Core\User;

use Eccube\Entity\Customer;
use Eccube\Entity\Member;
use Eccube\Security\Core\User\CustomerProvider;
use Eccube\Security\Core\User\MemberProvider;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private CustomerProvider $customerProvider;
    private MemberProvider $memberProvider;

    public function __construct(CustomerProvider $customerProvider, MemberProvider $memberProvider)
    {
        $this->customerProvider = $customerProvider;
        $this->memberProvider = $memberProvider;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass(string $class)
    {
        return Member::class === $class
            || is_subclass_of($class, Member::class)
            || Customer::class === $class
            || is_subclass_of($class, Customer::class);
    }

    public function loadUserByUsername(string $username)
    {
        try {
            $user = $this->customerProvider->loadUserByUsername($username);
        } catch (UserNotFoundException $e) {
            try {
                $user = $this->memberProvider->loadUserByUsername($username);
            } catch (UserNotFoundException $e) {
                throw new UserNotFoundException(sprintf('Username "%s" does not exist.', $username));
            }
        }

        return $user;
    }
}
