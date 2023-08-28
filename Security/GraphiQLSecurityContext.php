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

namespace Plugin\Api42\Security;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Customer;
use Eccube\Http\RequestStack;
use Eccube\Security\SecurityContext;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class GraphiQLSecurityContext extends SecurityContext
{
    private RequestStack $requestStack;
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker,
        CsrfTokenManagerInterface $csrfTokenManager,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
        ContainerInterface $container
    ) {
        parent::__construct($tokenStorage, $authorizationChecker, $csrfTokenManager);
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
        $this->container = $container;
    }

    public function getLoginUser()
    {
        $token = $this->getToken();
        if (!$token) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $bearerToken = $request->headers->get('Authorization');
            if ($bearerToken) {
                $rawJwt = \trim((string) \preg_replace('/^\s*Bearer\s/', '', $bearerToken));

                try {
                    $jwt = (new JwtFacade())->parse(
                        $rawJwt,
                        new SignedWith(new Sha256, InMemory::file($this->container->getParameter('plugin_data_realdir').'/Api42/oauth/public.key')),
                        new StrictValidAt(SystemClock::fromSystemTimezone())
                    );

                    $identifier = $jwt->claims()->get(RegisteredClaims::SUBJECT);

                    return $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => $identifier]);
                } catch (\Exception $e) {
                    log_error($e->getMessage(), [$e]);

                    return null;
                }
            }
        }

        return $token->getUser();
    }
}
