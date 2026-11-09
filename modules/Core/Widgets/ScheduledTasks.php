<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use Modules\Core\Scheduler\ScheduledTasks as Tasks;
use Modules\Core\Scheduler\TaskResult;

/**
 * Core widget "Scheduled tasks": the active tasks whose last run failed (with a link to the
 * scheduler screen), or a confirmation that every task ran correctly. Needs `schedule.list`.
 */
final class ScheduledTasks extends Widget
{
    public function id(): string
    {
        return 'core.scheduled-tasks';
    }

    public function title(): string
    {
        return lang('Core.widgets.scheduledTasks.title');
    }

    public function icon(): string
    {
        return 'bi bi-clock-history';
    }

    public function permission(): string
    {
        return 'schedule.list';
    }

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::SMALL, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'Core.widgets.scheduledTasks.limit',
            ],
        ];
    }

    public function render(array $options): string
    {
        $tasks  = new Tasks();
        $rows   = array_values(array_filter($tasks->rows(), static fn (array $row): bool => (int) $row['active'] === 1));
        $failed = array_values(array_filter($rows, static fn (array $row): bool => $row['last_status'] === TaskResult::FAILED));
        $items  = [];

        foreach (array_slice($failed, 0, (int) $options['limit']) as $row) {
            $task    = $tasks->instance((string) $row['class']);
            $items[] = [
                'name'    => $task !== null ? $task->name() : (string) $row['class'],
                'module'  => (string) $row['module'],
                'lastRun' => $row['last_run_at'] === null ? null : (string) $row['last_run_at'],
            ];
        }

        $next = null;

        foreach ($rows as $row) {
            if ($row['next_run_at'] !== null && ($next === null || (string) $row['next_run_at'] < $next)) {
                $next = (string) $row['next_run_at'];
            }
        }

        return $this->view('Modules\Core\Views\widgets\scheduled_tasks', [
            'items'  => $items,
            'failed' => count($failed),
            'total'  => count($rows),
            'next'   => $next,
            'url'    => site_url('backend/schedule'),
        ]);
    }
}
