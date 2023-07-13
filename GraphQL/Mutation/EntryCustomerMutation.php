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

namespace Plugin\Api42\GraphQL\Mutation;

use Eccube\Entity\Customer;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Entity\Master\Pref;
use Eccube\ORM\EntityManager;
use Eccube\Repository\CustomerRepository;
use Eccube\Routing\Generator\UrlGeneratorInterface;
use Eccube\Routing\Router;
use Eccube\Security\Core\User\UserPasswordHasher;
use Eccube\Service\MailService;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;

class EntryCustomerMutation implements Mutation
{
    private Types $types;

    private EntityManager $entityManager;

    private UserPasswordHasher $passwordHasher;

    private CustomerRepository $customerRepository;

    private MailService $mailService;

    private Router $router;

    public function __construct(
        Types $types,
        UserPasswordHasher $passwordHasher,
        CustomerRepository $customerRepository,
        EntityManager $entityManager,
        MailService $mailService,
        Router $router
    ) {
        $this->types = $types;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->customerRepository = $customerRepository;
        $this->mailService = $mailService;
        $this->router = $router;
    }

    public function getName()
    {
        return 'entryCustomer';
    }

    public function getMutation()
    {
        return [
            'type' => $this->types->get(Customer::class),
            'args' => [
                'name01' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Name01'),
                ],
                'name02' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Name02'),
                ],
                'kana01' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Kana01'),
                ],
                'kana02' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Kana02'),
                ],
                'postal_code' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('PostalCode'),
                ],
                'addr01' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Addr01'),
                ],
                'addr02' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Addr02'),
                ],
                'pref' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Pref'),
                ],
                'email' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('Email'),
                ],
                'plain_password' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('PlainPassword'),
                ],
                'phone_number' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => trans('PhoneNumber'),
                ],
            ],
            'resolve' => [$this, 'entryCustomer'],
        ];
    }

    public function entryCustomer($root, $args)
    {
        $customer = new Customer();
        $password = $this->passwordHasher->hashPassword($customer, $args['plain_password']);

        $customerStatusProvisional = $this->entityManager
            ->find(CustomerStatus::class, CustomerStatus::PROVISIONAL);

        $pref = $this->entityManager
            ->find(Pref::class, $args['pref']);

        $customer
            ->setName01($args['name01'])
            ->setName02($args['name02'])
            ->setKana01($args['kana01'])
            ->setKana02($args['kana02'])
            ->setPostalCode($args['postal_code'])
            ->setPref($pref)
            ->setAddr01($args['addr01'])
            ->setAddr02($args['addr02'])
            ->setPhoneNumber($args['phone_number'])
            ->setEmail($args['email'])
            ->setPassword($password)
            ->setStatus($customerStatusProvisional)
            ->setSecretKey($this->customerRepository->getUniqueSecretKey())
            ->setPoint(0);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $activateUrl = $this->router->generate('entry_activate',
            ['secret_key' => $customer->getSecretKey()],
            UrlGeneratorInterface::ABSOLUTE_URL);

        $this->mailService->sendCustomerConfirmMail($customer, $activateUrl);

        return $customer;
    }
}
