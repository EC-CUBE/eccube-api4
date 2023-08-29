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

use Eccube\Entity\AbstractEntity;
use Eccube\Entity\Customer;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\ORM\EntityManager;
use Eccube\Repository\CustomerRepository;
use Eccube\Routing\Generator\UrlGeneratorInterface;
use Eccube\Routing\Router;
use Eccube\Security\Core\User\UserPasswordHasher;
use Eccube\Service\MailService;
use Plugin\Api42\Form\Type\Front\EntryType;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactoryInterface;

class EntryCustomerMutation extends AbstractMutation
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
        Router $router,
        FormFactoryInterface $formFactory
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->customerRepository = $customerRepository;
        $this->mailService = $mailService;
        $this->router = $router;
        $this->setTypes($types);
        $this->setFormFactory($formFactory);
    }

    public function getName(): string
    {
        return 'entryCustomer';
    }

    /**
     * @return class-string<Customer>
     */
    public function getTypesClass(): string
    {
        return Customer::class;
    }

    /**
      * @return class-string<EntryType>
     */
    public function getArgsType(): string
    {
        return EntryType::class;
    }

    /**
     * @template T of Customer
     * @param mixed $root
     * @param T $args
     * @return T
     */
    protected function executeMutation($root, $args): mixed
    {
        /** @var Customer $customer */
        $customer = $args;
        $password = $this->passwordHasher->hashPassword($customer, $customer->getPlainPassword());

        $customerStatusProvisional = $this->entityManager
            ->find(CustomerStatus::class, CustomerStatus::PROVISIONAL);

        $customer
            ->setPassword($password)
            ->setStatus($customerStatusProvisional)
            ->setSecretKey($this->customerRepository->getUniqueSecretKey())
            ->setPoint('0');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $activateUrl = $this->router->generate('entry_activate',
            ['secret_key' => $customer->getSecretKey()],
            UrlGeneratorInterface::ABSOLUTE_URL);

        $this->mailService->sendCustomerConfirmMail($customer, $activateUrl);

        return $customer;
    }
}
