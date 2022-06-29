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

namespace Plugin\Api42;

use Doctrine\ORM\EntityManager;
use Eccube\Entity\AuthorityRole;
use Eccube\Entity\Master\Authority;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\AuthorityRoleRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    private $denyUrl = '/api';

    /**
     * {@inheritdoc}
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createAuthorityRole($container);
    }

    /**
     * {@inheritdoc}
     */
    public function disable(array $meta, ContainerInterface $container)
    {
        $this->removeAuthorityRole($container);
    }

    private function createAuthorityRole(ContainerInterface $container)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        /** @var Authority $Authority */
        $Authority = $entityManager->find(Authority::class, Authority::OWNER);

        $AuthorityRole = new AuthorityRole();
        $AuthorityRole->setAuthority($Authority);
        $AuthorityRole->setDenyUrl($this->denyUrl);

        $entityManager->persist($AuthorityRole);
        $entityManager->flush($AuthorityRole);
    }

    private function removeAuthorityRole(ContainerInterface $container)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        /** @var AuthorityRoleRepository $AuthorityRoleRepository */
        $AuthorityRoleRepository = $entityManager->getRepository(AuthorityRole::class);

        $AuthorityRole = $AuthorityRoleRepository->findOneBy(['deny_url' => $this->denyUrl]);

        if (!is_null($AuthorityRole)) {
            $entityManager->remove($AuthorityRole);
            $entityManager->flush($AuthorityRole);
        }
    }
}
