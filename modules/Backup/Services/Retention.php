<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use CodeIgniter\I18n\Time;

/**
 * Retention of backup files (settings `Backup.keepDaily`, `keepWeekly`, `keepMonthly`): the
 * newest backup of each of the last N days, ISO weeks and months that have backups is kept; the
 * rest is deleted. Files younger than an hour are never deleted (a backup may be in use), and
 * every tier at 0 keeps everything.
 */
final class Retention
{
    public function __construct(private readonly BackupRepository $repository = new BackupRepository())
    {
    }

    /**
     * The files to delete.
     *
     * @param list<array{file: string, created_at: string}> $backups successful backups
     *
     * @return list<string>
     */
    public static function select(array $backups, int $daily, int $weekly, int $monthly, Time $now): array
    {
        if ($daily <= 0 && $weekly <= 0 && $monthly <= 0) {
            return [];
        }

        usort($backups, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']) ?: strcmp($b['file'], $a['file']));

        $keep = [];

        foreach (['Y-m-d' => $daily, 'o-\WW' => $weekly, 'Y-m' => $monthly] as $format => $count) {
            $periods = [];

            foreach ($backups as $backup) {
                $period = Time::parse($backup['created_at'], 'UTC')->format($format);

                if (! isset($periods[$period])) {
                    if (count($periods) >= $count) {
                        break;
                    }

                    $periods[$period] = true;
                    $keep[$backup['file']] = true;
                }
            }
        }

        $limit  = $now->subHours(1)->format('Y-m-d H:i:s');
        $delete = [];

        foreach ($backups as $backup) {
            if (! isset($keep[$backup['file']]) && $backup['created_at'] < $limit) {
                $delete[] = $backup['file'];
            }
        }

        return $delete;
    }

    /**
     * Applies the retention settings; returns the deleted files.
     *
     * @return list<string>
     */
    public function prune(?Time $now = null): array
    {
        $this->repository->reconcile();
        $backups = array_map(static fn (array $row): array => ['file' => (string) $row['file'], 'created_at' => (string) $row['created_at']], $this->repository->successful());
        $delete  = self::select(
            $backups,
            (int) setting('Backup.keepDaily'),
            (int) setting('Backup.keepWeekly'),
            (int) setting('Backup.keepMonthly'),
            $now ?? Time::now('UTC'),
        );

        foreach ($delete as $file) {
            $this->repository->delete($file);
        }

        if ($delete !== []) {
            activity_log('Backup', 'prune', ['resource' => 'backup', 'summary' => (string) count($delete), 'changes' => ['deleted' => $delete]]);
        }

        return $delete;
    }
}
