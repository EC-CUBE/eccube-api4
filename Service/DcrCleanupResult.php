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

/**
 * DCR 死蔵クライアント掃除の結果。
 */
final readonly class DcrCleanupResult
{
    /**
     * @param list<string> $deletedClientIdentifiers 削除した (dry-run では削除対象の) client 識別子
     * @param int          $keptActiveCount          有効トークンを持つため残した件数
     * @param int          $examinedCount            grace を過ぎて検査した総件数
     * @param bool         $dryRun                   true なら実削除せず対象を集計しただけ
     */
    public function __construct(
        public array $deletedClientIdentifiers,
        public int $keptActiveCount,
        public int $examinedCount,
        public bool $dryRun,
    ) {
    }

    public function deletedCount(): int
    {
        return \count($this->deletedClientIdentifiers);
    }
}
