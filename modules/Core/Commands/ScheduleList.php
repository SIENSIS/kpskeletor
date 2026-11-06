<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Scheduler\TaskRunner;

/**
 * php spark schedule:list
 *
 * The scheduled tasks of the enabled modules with their schedule, next run and last result.
 */
final class ScheduleList extends BaseCommand
{
    protected $group       = 'Scheduler';
    protected $name        = 'schedule:list';
    protected $description = 'Lists the scheduled tasks with their schedule, next run and last result.';
    protected $usage       = 'schedule:list';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        if (! db_connect()->tableExists('scheduled_tasks')) {
            CLI::error(lang('Core.schedule.notInstalled'));

            return EXIT_ERROR;
        }

        $runner = new TaskRunner();
        $tasks  = $runner->tasks();
        $tasks->sync(TaskRunner::now());
        $rows = [];

        foreach ($tasks->rows() as $row) {
            $task     = $tasks->instance((string) $row['class']);
            $schedule = $task?->schedule();
            $active   = (int) $row['active'] === 1 && $task !== null;
            $note     = match (true) {
                ! $active                                                 => lang('Core.schedule.inactive'),
                $schedule !== null && ! $schedule->runsInEnvironment(ENVIRONMENT) => lang('Core.schedule.otherEnvironment', [implode(', ', $schedule->environments())]),
                default                                                   => '',
            };

            $rows[] = [
                $task !== null ? $task->name() : (string) $row['class'],
                (string) $row['module'],
                (string) $row['expression'],
                $active ? (string) ($row['next_run_at'] ?? '-') : '-',
                $row['last_run_at'] !== null ? $row['last_run_at'] . ' (' . lang('Core.schedule.status.' . $row['last_status']) . ')' : '-',
                $note,
            ];
        }

        if ($rows === []) {
            CLI::write(lang('Core.schedule.empty'));

            return EXIT_SUCCESS;
        }

        CLI::table($rows, [
            lang('Core.schedule.task'),
            lang('Core.schedule.module'),
            lang('Core.schedule.expression'),
            lang('Core.schedule.nextRun'),
            lang('Core.schedule.lastRun'),
            lang('Core.schedule.notes'),
        ]);

        return EXIT_SUCCESS;
    }
}
