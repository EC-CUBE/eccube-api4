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

namespace Plugin\Api44\Command;

use Plugin\Api44\Service\DcrClientCleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * DCR (動的クライアント登録) で量産される死蔵クライアントを掃除する。
 *
 * 登録から --days を過ぎ、 有効な access/refresh トークンを持たない DCR クライアントを削除する。
 * 定期実行 (cron) を想定。 --dry-run で対象だけ確認できる。
 * 掃除対象は追跡レコードのある client のみ (導入前/記録漏れの client は対象外)。
 */
#[AsCommand(
    name: 'eccube:api:mcp:cleanup-dcr-clients',
    description: 'Remove abandoned DCR-registered OAuth clients that have no valid tokens.'
)]
class CleanupDcrClientsCommand extends Command
{
    private const DEFAULT_GRACE_DAYS = 30;

    public function __construct(
        private readonly DcrClientCleaner $cleaner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Grace period in days; clients registered within this window are never removed', (string) self::DEFAULT_GRACE_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List clients that would be removed without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT);
        if (false === $days || $days < 0) {
            $io->error('--days must be a non-negative integer.');

            return Command::INVALID;
        }
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->cleaner->cleanup(new \DateTime(), $days, $dryRun);
        } catch (\Throwable $e) {
            // 破壊的操作の失敗を握り潰さず記録する (トランザクションは rollback 済み = 未変更)
            $this->logger->error('DCR cleanup failed', ['exception' => $e, 'days' => $days, 'dry_run' => $dryRun]);
            $io->error('DCR cleanup failed; no clients were removed. See logs for details.');

            return Command::FAILURE;
        }

        // 破壊的・自動実行の監査証跡として、 いつ何件消したかを残す
        $this->logger->info('DCR cleanup completed', [
            'dry_run' => $dryRun,
            'examined' => $result->examinedCount,
            'kept_active' => $result->keptActiveCount,
            'deleted' => $result->deletedCount(),
            'deleted_client_ids' => $result->deletedClientIdentifiers,
        ]);

        $io->title($dryRun ? 'DCR cleanup (dry-run)' : 'DCR cleanup');
        $io->listing($result->deletedClientIdentifiers);
        $io->table(['examined', 'kept (active)', $dryRun ? 'to delete' : 'deleted'], [
            [$result->examinedCount, $result->keptActiveCount, $result->deletedCount()],
        ]);
        $io->success(sprintf(
            '%s %d abandoned DCR client(s); kept %d active.',
            $dryRun ? 'Would remove' : 'Removed',
            $result->deletedCount(),
            $result->keptActiveCount,
        ));

        return Command::SUCCESS;
    }
}
