<?php

declare(strict_types=1);

namespace Modules\Backup\Widgets;

use Modules\Backup\Services\BackupRepository;
use Modules\Backup\Tasks\ScheduledBackup;
use Modules\Core\Scheduler\ScheduledTasks;
use Modules\Core\Widgets\Widget;
use Modules\Core\Widgets\WidgetSizes;

/**
 * Widget "Backups": the last backup (result, date, type, size) and the next scheduled run.
 * Needs `backup.list`.
 */
final class Backups extends Widget
{
    public function id(): string
    {
        return 'backup.backups';
    }

    public function title(): string
    {
        return lang('Backup.widget.title');
    }

    public function icon(): string
    {
        return 'bi bi-archive';
    }

    public function permission(): string
    {
        return 'backup.list';
    }

    public function sizes(): array
    {
        return [WidgetSizes::SMALL, WidgetSizes::MEDIUM, WidgetSizes::LARGE];
    }

    public function render(array $options): string
    {
        $repository = new BackupRepository();
        $repository->reconcile();
        $scheduled = (bool) setting('Backup.scheduled');
        $next      = null;

        foreach ((new ScheduledTasks())->rows() as $row) {
            if ($row['class'] === ScheduledBackup::class && (int) $row['active'] === 1 && $row['next_run_at'] !== null) {
                $next = (string) $row['next_run_at'];
            }
        }

        return $this->view('Modules\Backup\Views\widget', [
            'last'      => $repository->latest(),
            'scheduled' => $scheduled,
            'next'      => $next,
            'url'       => site_url('backend/backups'),
        ]);
    }
}
