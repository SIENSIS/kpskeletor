<?php

declare(strict_types=1);

namespace Modules\Tasks\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\Tasks\Notifications\TaskOverdue;
use Modules\Tasks\Services\TaskRepository;

/**
 * Scheduled task (tutorial 7; this folder holds the module's **scheduled tasks**, not to-do
 * tasks): every day at 07:30, reminds the assignees of each open to-do task past its due date
 * (plus `Tasks.reminderGraceDays`, tutorial 8) with the notification `TaskOverdue`. A to-do task
 * is reminded at most once a day (`reminded_on`), so running it twice the same day sends
 * nothing new; `withoutOverlapping()` keeps two runs from overlapping. The output is shown in
 * Settings › Scheduled tasks.
 */
final class OverdueReminder implements TaskInterface
{
    public function name(): string
    {
        return lang('Tasks.scheduled.overdueReminder');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('07:30')->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $repository = new TaskRepository();
        $grace      = max(0, min(30, (int) setting('Tasks.reminderGraceDays')));
        $tasks      = $repository->overdue($grace);
        $sent       = 0;
        $reminded   = 0;

        foreach ($tasks as $task) {
            if ($task['assignees'] !== []) {
                $sent += notify($task['assignees'], new TaskOverdue([
                    'id'     => (int) $task['id'],
                    'title'  => (string) $task['title'],
                    'due_on' => (string) $task['due_on'],
                ]));
                $reminded++;
            }

            $repository->markReminded((int) $task['id']);
        }

        $output = lang('Tasks.scheduled.overdueReminderOutput', [$reminded, $sent]);

        if ($reminded > 0) {
            activity_log('Tasks', 'overdue_reminded', [
                'resource' => 'tasks-tasks',
                'summary'  => $output,
                'changes'  => ['tasks' => $reminded, 'notifications' => $sent],
            ]);
        }

        return TaskResult::success($output);
    }
}
