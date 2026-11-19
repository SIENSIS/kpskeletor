<?php

declare(strict_types=1);

namespace Modules\Tasks\Widgets;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Widgets\Widget;
use Modules\Tasks\Services\TaskRepository;

/**
 * Widget "My pending to-do tasks" (tutorial 4, server-side): the open to-do tasks assigned to the
 * user, the soonest due first, with a link to the board. Needs `tasks-board.view`.
 */
final class MyPendingTasks extends Widget
{
    public function id(): string
    {
        return 'tasks.pending';
    }

    public function title(): string
    {
        return lang('Tasks.widgets.pending.title');
    }

    public function icon(): string
    {
        return 'bi bi-check2-square';
    }

    public function permission(): string
    {
        return 'tasks-board.view';
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'Tasks.widgets.limit',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $repository = new TaskRepository();
        $rows       = $repository->pendingFor((int) $user->id, (int) $options['limit']);

        if ($rows === []) {
            return $this->empty(lang('Tasks.widgets.pending.empty'), 'bi bi-check2-all');
        }

        $today = date('Y-m-d');
        $items = array_map(static fn (array $row): array => [
            'title'    => (string) $row['title'],
            'status'   => (string) $row['status'],
            'priority' => (string) $row['priority'],
            'due'      => is_string($row['due_on']) ? $row['due_on'] : null,
            'overdue'  => is_string($row['due_on']) && $row['due_on'] < $today,
        ], $rows);

        return $this->view('Modules\Tasks\Views\widgets\pending', [
            'items' => $items,
            'total' => $repository->pendingCount((int) $user->id),
            'url'   => backend_url('tasks'),
        ]);
    }
}
