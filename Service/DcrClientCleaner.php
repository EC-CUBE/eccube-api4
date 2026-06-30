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

namespace Plugin\Api44\Service;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use Plugin\Api44\Entity\DcrClient;
use Plugin\Api44\Repository\DcrClientRepository;
use Psr\Log\LoggerInterface;

/**
 * 動的クライアント登録 (DCR) で量産される死蔵クライアントを掃除する。
 *
 * 掃除対象 = 登録から grace 日数を過ぎ、 かつ有効な (失効/期限切れでない) access / refresh
 * トークンを 1 つも持たない DCR クライアント。 再接続時は DCR でまた登録されるため削除して問題ない。
 * in-flight (登録直後で同意未完了) の client を消さないよう grace で守る。
 *
 * トークンの FK: oauth2_access_token.client は ON DELETE CASCADE (client 削除で access も消える)。
 * 一方 refresh は client への直接 FK が無く access_token への FK が ON DELETE SET NULL のため、
 * client を消すだけでは孤立 refresh 行が残る。 削除時に refresh を明示削除して残骸を残さない。
 *
 * 掃除対象は追跡レコード (plg_api_dcr_client) のある client のみ。 機能導入前に作られた client や
 * 登録時の記録に失敗した client は対象外 (作成時刻が無く grace 判定できないため)。
 */
class DcrClientCleaner
{
    public function __construct(
        private readonly DcrClientRepository $dcrClientRepository,
        private readonly ClientManagerInterface $clientManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function cleanup(\DateTimeInterface $now, int $graceDays, bool $dryRun): DcrCleanupResult
    {
        $threshold = \DateTimeImmutable::createFromInterface($now)
            ->sub(new \DateInterval(sprintf('P%dD', $graceDays)));

        $candidates = $this->dcrClientRepository->findRegisteredBefore($threshold);

        // 副作用なしで分類する (有効トークンを持つものは残す)
        $dead = [];
        $keptActive = 0;
        foreach ($candidates as $record) {
            if ($this->hasLiveToken($record->getClientIdentifier(), $now)) {
                ++$keptActive;
                continue;
            }
            $dead[] = $record;
        }
        $deletedIdentifiers = array_map(static fn (DcrClient $r): string => $r->getClientIdentifier(), $dead);

        // 削除は 1 トランザクションにまとめ、 途中失敗で「client だけ消えて追跡レコードが残る」
        // 不整合を防ぐ (全件成功 or 全件未変更)。 失敗時は例外を呼び出し側 (コマンド) へ伝える。
        if (!$dryRun && [] !== $dead) {
            $this->entityManager->wrapInTransaction(function () use ($dead): void {
                foreach ($dead as $record) {
                    $this->deleteDeadClient($record);
                }
            });
        }

        return new DcrCleanupResult($deletedIdentifiers, $keptActive, \count($candidates), $dryRun);
    }

    private function deleteDeadClient(DcrClient $record): void
    {
        $identifier = $record->getClientIdentifier();

        // client 削除では access への SET NULL で孤立する refresh を、 access が残っているうちに先に消す
        $this->entityManager->createQuery(
            'DELETE FROM '.RefreshToken::class.' r WHERE IDENTITY(r.accessToken) IN'
            .' (SELECT a.identifier FROM '.AccessToken::class.' a WHERE IDENTITY(a.client) = :client)'
        )->setParameter('client', $identifier)->execute();

        $client = $this->clientManager->find($identifier);
        if (null !== $client) {
            // client 削除で access は DB の ON DELETE CASCADE で消える (上の refresh 削除は access がまだ在る間に実行済み)
            $this->entityManager->remove($client);
        } else {
            // 追跡レコードはあるが league client が無い = 別経路削除 or 過去の部分失敗の残骸。 兆候として記録する
            $this->logger->notice('DCR cleanup: tracking record points to a missing OAuth client; removing orphan record', ['client_id' => $identifier]);
        }
        $this->entityManager->remove($record);
    }

    /**
     * クライアントが現在も利用可能か (有効な access または refresh トークンを持つか) を判定する。
     */
    private function hasLiveToken(string $clientIdentifier, \DateTimeInterface $now): bool
    {
        // token の expiry は datetime_immutable (naive local 保存)。 :now を型指定せず束縛すると
        // tz 変換で比較がズレるため、 同じ型で束縛して naive local 同士で比較する。
        $nowParam = \DateTimeImmutable::createFromInterface($now);

        $accessCount = (int) $this->entityManager->createQuery(
            'SELECT COUNT(a.identifier) FROM '.AccessToken::class.' a'
            .' WHERE IDENTITY(a.client) = :client AND a.revoked = false AND a.expiry > :now'
        )
            ->setParameter('client', $clientIdentifier)
            ->setParameter('now', $nowParam, Types::DATETIME_IMMUTABLE)
            ->getSingleScalarResult();
        if ($accessCount > 0) {
            return true;
        }

        $refreshCount = (int) $this->entityManager->createQuery(
            'SELECT COUNT(r.identifier) FROM '.RefreshToken::class.' r JOIN r.accessToken a'
            .' WHERE IDENTITY(a.client) = :client AND r.revoked = false AND r.expiry > :now'
        )
            ->setParameter('client', $clientIdentifier)
            ->setParameter('now', $nowParam, Types::DATETIME_IMMUTABLE)
            ->getSingleScalarResult();

        return $refreshCount > 0;
    }
}
