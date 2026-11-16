<?php

declare(strict_types=1);

namespace Modules\Backup\Tasks;

use Modules\Backup\Notifications\BackupResult;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupPassword;
use Modules\Backup\Services\BackupService;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * The scheduled backup: every night, when the setting `Backup.scheduled` is on, a backup of the
 * type `Backup.scheduledType` encrypted with `backup.password` from `.env` (never stored
 * anywhere else). The result is notified to the holders of `backup.list` (BackupResult).
 */
final class ScheduledBackup implements TaskInterface
{
    public function __construct(private readonly ?BackupService $service = null, private readonly BackupPassword $passwords = new BackupPassword())
    {
    }

    public function name(): string
    {
        return lang('Backup.task.scheduled');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('02:41')->withoutOverlapping(240);
    }

    public function run(): TaskResult
    {
        if (! (bool) setting('Backup.scheduled')) {
            return TaskResult::skipped(lang('Backup.task.off'));
        }

        $password = $this->passwords->configured();
        $type     = (string) setting('Backup.scheduledType');

        try {
            if ($password === null) {
                throw new BackupException(lang('Backup.error.noPassword'));
            }

            @set_time_limit(0);
            $row = ($this->service ?? new BackupService())->create($password, in_array($type, ['full', 'db'], true) ? $type : 'full', 'schedule');
        } catch (BackupException $e) {
            notifyPermission('backup.list', new BackupResult(['result' => 'failed', 'message' => $e->getMessage()]));

            return TaskResult::failure($e->getMessage());
        }

        notifyPermission('backup.list', new BackupResult(['result' => 'success', 'file' => (string) $row['file'], 'size' => (int) $row['size']]));

        return TaskResult::success(lang('Backup.msg.created', [(string) $row['file'], ui_bytes((int) $row['size'])]));
    }
}
