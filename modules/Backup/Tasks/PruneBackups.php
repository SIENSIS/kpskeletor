<?php

declare(strict_types=1);

namespace Modules\Backup\Tasks;

use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\BackupService;
use Modules\Backup\Services\JobStore;
use Modules\Backup\Services\Retention;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Applies the retention settings (`Backup.keepDaily`, `keepWeekly`, `keepMonthly`) every
 * night, after the scheduled backup, like `php spark backup:prune`; also deletes the progress
 * files of old restores and temporary folders left by an interrupted backup.
 */
final class PruneBackups implements TaskInterface
{
    public function name(): string
    {
        return lang('Backup.task.prune');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('04:13')->withoutOverlapping(60);
    }

    public function run(): TaskResult
    {
        $deleted = (new Retention())->prune();
        (new JobStore())->purge();
        self::cleanTemporary(config(BackupConfig::class));

        return TaskResult::success(lang('Backup.msg.pruned', [count($deleted)]));
    }

    /**
     * Deletes temporary folders (`writable/backups/tmp/*`) older than a day.
     */
    public static function cleanTemporary(BackupConfig $config): void
    {
        foreach (glob(rtrim($config->path, '/') . '/tmp/*', GLOB_ONLYDIR) ?: [] as $folder) {
            if ((int) filemtime($folder) < time() - DAY) {
                BackupService::removeFolder($folder);
            }
        }
    }
}
