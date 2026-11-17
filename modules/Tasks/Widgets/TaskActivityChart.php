<?php

declare(strict_types=1);

namespace Modules\Tasks\Widgets;

use Modules\Core\Widgets\AsyncWidget;
use Modules\Core\Widgets\WidgetSizes;
use Modules\Tasks\Services\TaskRepository;

/**
 * Widget "To-do task activity" (tutorial 4, asynchronous): to-do tasks created and completed per
 * day over the last days (option `days`), as a small SVG bar chart drawn on the server from
 * `data()`. The dashboard loads it from `backend/dashboard/widgets/tasks.activity/data` (403
 * without the permission or when hidden for the user's groups). Needs `tasks-tasks.list`.
 */
final class TaskActivityChart extends AsyncWidget
{
    public function id(): string
    {
        return 'tasks.activity';
    }

    public function title(): string
    {
        return lang('Tasks.widgets.activity.title');
    }

    public function icon(): string
    {
        return 'bi bi-bar-chart';
    }

    public function permission(): string
    {
        return 'tasks-tasks.list';
    }

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function settings(): array
    {
        return [
            'days' => [
                'type'    => 'int',
                'default' => 14,
                'min'     => 7,
                'max'     => 60,
                'label'   => 'Tasks.widgets.activity.days',
            ],
        ];
    }

    public function cacheSeconds(): int
    {
        return 300;
    }

    /**
     * @return array{days: list<array{date: string, created: int, completed: int}>, created: int, completed: int}
     */
    public function data(array $options): array
    {
        $days = (new TaskRepository())->activity(max(7, min(60, (int) ($options['days'] ?? 14))));

        return [
            'days'      => $days,
            'created'   => array_sum(array_column($days, 'created')),
            'completed' => array_sum(array_column($days, 'completed')),
        ];
    }

    public function renderData(array $data, array $options): string
    {
        $days = is_array($data['days'] ?? null) ? $data['days'] : [];
        $max  = 1;

        foreach ($days as $day) {
            $max = max($max, (int) $day['created'], (int) $day['completed']);
        }

        return $this->view('Modules\Tasks\Views\widgets\activity', [
            'days'      => $days,
            'max'       => $max,
            'created'   => (int) ($data['created'] ?? 0),
            'completed' => (int) ($data['completed'] ?? 0),
        ]);
    }
}
