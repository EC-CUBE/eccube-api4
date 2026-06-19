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

namespace Plugin\Api44\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Tests\EccubeTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Plugin\Api44\Entity\DcrClient;
use Plugin\Api44\Repository\DcrClientRepository;
use Plugin\Api44\Service\DcrClientCleaner;

/**
 * DCR 死蔵クライアント掃除の契約テスト。
 *
 * grace を過ぎ有効トークンを持たない DCR クライアントだけを削除し、 grace 内・有効トークン持ち・
 * dry-run は消さないことを実挙動で担保する。
 */
class DcrClientCleanerTest extends EccubeTestCase
{
    private ?DcrClientCleaner $cleaner = null;
    private ?DcrClientRepository $dcrClientRepository = null;
    private ?ClientManagerInterface $clientManager = null;
    private ?AccessTokenManagerInterface $accessTokenManager = null;
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleaner = static::getContainer()->get(DcrClientCleaner::class);
        $this->dcrClientRepository = static::getContainer()->get(DcrClientRepository::class);
        $this->clientManager = static::getContainer()->get(ClientManagerInterface::class);
        $this->accessTokenManager = static::getContainer()->get(AccessTokenManagerInterface::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDeletesAbandonedClientPastGrace(): void
    {
        $id = $this->registerDcrClient('dcr-old-notoken', 40);

        $result = $this->runCleanup(30, false);

        $this->assertContains($id, $result->deletedClientIdentifiers);
        $this->assertNull($this->clientManager->find($id), 'league client は削除される');
        $this->assertNull($this->dcrClientRepository->findOneBy(['clientIdentifier' => $id]), '追跡レコードも削除される');
    }

    public function testKeepsClientWithinGrace(): void
    {
        $id = $this->registerDcrClient('dcr-recent', 5);

        $result = $this->runCleanup(30, false);

        $this->assertNotContains($id, $result->deletedClientIdentifiers);
        $this->assertNotNull($this->clientManager->find($id), 'grace 内の client は残る');
    }

    public function testKeepsClientWithLiveAccessToken(): void
    {
        $id = $this->registerDcrClient('dcr-old-live', 40);
        $this->issueAccessToken($id, new \DateTimeImmutable('+1 hour'), false);

        $result = $this->runCleanup(30, false);

        $this->assertNotContains($id, $result->deletedClientIdentifiers);
        $this->assertSame(1, $result->keptActiveCount);
        $this->assertNotNull($this->clientManager->find($id), '有効トークンを持つ client は残る');
    }

    public function testDeletesClientWithOnlyExpiredToken(): void
    {
        $id = $this->registerDcrClient('dcr-old-expired', 40);
        $this->issueAccessToken($id, new \DateTimeImmutable('-1 hour'), false);

        $result = $this->runCleanup(30, false);

        $this->assertContains($id, $result->deletedClientIdentifiers);
        $this->assertNull($this->clientManager->find($id), '期限切れトークンのみの client は削除される');
    }

    public function testDryRunReportsButDeletesNothing(): void
    {
        $id = $this->registerDcrClient('dcr-old-dryrun', 40);

        $result = $this->runCleanup(30, true);

        $this->assertTrue($result->dryRun);
        $this->assertContains($id, $result->deletedClientIdentifiers, 'dry-run でも対象として報告する');
        $this->assertNotNull($this->clientManager->find($id), 'dry-run では実削除しない');
        $this->assertNotNull($this->dcrClientRepository->findOneBy(['clientIdentifier' => $id]), 'dry-run では追跡レコードも残す');
    }

    /**
     * DCR クライアント (league Client + 追跡レコード) を作り、 登録時刻を $ageDays 日前にする。
     */
    private function registerDcrClient(string $identifier, int $ageDays): string
    {
        $client = new Client('DCR:test', $identifier, null);
        $client->setActive(true);
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('mcp:product:read'));
        $this->clientManager->save($client);

        $record = new DcrClient();
        $record->setClientIdentifier($identifier);
        $record->setCreateDate(new \DateTime());
        $this->em->persist($record);
        $this->em->flush();

        // EC-CUBE の timestampable が persist 時に create_date を now で上書きするため、
        // 過去の登録時刻は lifecycle を迂回する DQL UPDATE で直接設定する。
        $this->em->createQuery(
            'UPDATE '.DcrClient::class.' d SET d.createDate = :date WHERE d.clientIdentifier = :id'
        )
            ->setParameter('date', (new \DateTime())->modify(sprintf('-%d days', $ageDays)))
            ->setParameter('id', $identifier)
            ->execute();

        return $identifier;
    }

    private function issueAccessToken(string $clientIdentifier, \DateTimeInterface $expiry, bool $revoked): void
    {
        $client = $this->clientManager->find($clientIdentifier);
        $token = new AccessToken('at-'.$clientIdentifier, $expiry, $client, 'admin', [new Scope('mcp:product:read')]);
        if ($revoked) {
            $token->revoke();
        }
        $this->accessTokenManager->save($token);
        $this->em->flush();
    }

    /**
     * 準備した DB 状態を確定し、 EM を空にしてから掃除を実行する
     * (cleaner が identity map の残存ではなく DB の実状態を見るようにする)。
     */
    private function runCleanup(int $graceDays, bool $dryRun): \Plugin\Api44\Service\DcrCleanupResult
    {
        $this->em->flush();
        $this->em->clear();

        return $this->cleaner->cleanup(new \DateTime(), $graceDays, $dryRun);
    }
}
