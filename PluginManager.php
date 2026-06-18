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

namespace Plugin\Api44;

use Doctrine\ORM\EntityManager;
use Eccube\Entity\AuthorityRole;
use Eccube\Entity\Master\Authority;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\AuthorityRoleRepository;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Plugin\Api44\Service\McpTokenService;
use Psr\Container\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    private string $denyUrl = '/api';

    /**
     * {@inheritdoc}
     */
    public function enable(array $meta, ContainerInterface $container): void
    {
        $this->createAuthorityRole($container);
        $this->createMcpPatClient($container);
    }

    /**
     * {@inheritdoc}
     */
    public function disable(array $meta, ContainerInterface $container): void
    {
        $this->removeAuthorityRole($container);
    }

    private function createAuthorityRole(ContainerInterface $container): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        /** @var Authority $Authority */
        $Authority = $entityManager->find(Authority::class, Authority::OWNER);

        $AuthorityRole = new AuthorityRole();
        $AuthorityRole->setAuthority($Authority);
        $AuthorityRole->setDenyUrl($this->denyUrl);

        $entityManager->persist($AuthorityRole);
        $entityManager->flush();
    }

    /**
     * MCP トークン (PAT) 発行に使う内部クライアントを冪等生成する。
     * league の ClientManager は private のため EntityManager 経由で永続化する。
     */
    private function createMcpPatClient(ContainerInterface $container): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        $existing = $entityManager->getRepository(Client::class)
            ->findOneBy(['identifier' => McpTokenService::CLIENT_IDENTIFIER]);
        if (null !== $existing) {
            return;
        }

        $client = new Client('MCP PAT', McpTokenService::CLIENT_IDENTIFIER, null);
        $client->setActive(true);
        $client->setGrants(new Grant('authorization_code'));
        $client->setScopes(...array_map(
            static fn (string $scope): Scope => new Scope($scope),
            McpTokenService::AVAILABLE_SCOPES,
        ));

        $entityManager->persist($client);
        $entityManager->flush();
    }

    private function removeAuthorityRole(ContainerInterface $container): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        /** @var AuthorityRoleRepository $AuthorityRoleRepository */
        $AuthorityRoleRepository = $entityManager->getRepository(AuthorityRole::class);

        $AuthorityRole = $AuthorityRoleRepository->findOneBy(['deny_url' => $this->denyUrl]);

        if (!is_null($AuthorityRole)) {
            $entityManager->remove($AuthorityRole);
            $entityManager->flush();
        }
    }
}
