<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Scheduler\TaskResult;
use Modules\Core\Scheduler\TaskRun;
use Modules\Core\Scheduler\TaskRunner;

/**
 * php spark schedule:run [--task=<name>] [--force]
 *
 * The single cron entry of the application (`* * * * * php /path/spark schedule:run`): runs the
 * scheduled tasks that are due (docs/scheduler.md).
 */
final class ScheduleRun extends BaseCommand
{
    protected $group       = 'Scheduler';
    protected $name        = 'schedule:run';
    protected $description = 'Runs the scheduled tasks that are due (the single cron entry, every minute).';
    protected $usage       = 'schedule:run [--task=<name>] [--force]';
    protected $options     = [
        '--task'  => 'Only this scheduled task (class or short class name), if it is due',
        '--force' => 'With --task: run it now even if it is not due',
    ];

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
        $task   = ModulesSync::option($params, 'task');
        $force  = ModulesSync::option($params, 'force') !== null;

        if ($task === true) {
            CLI::error(lang('Core.schedule.unknownTask', ['']));

            return EXIT_ERROR;
        }

        if ($force && ! is_string($task)) {
            CLI::error(lang('Core.schedule.forceNeedsTask'));

            return EXIT_ERROR;
        }

        if (is_string($task) && $task !== '') {
            try {
                $runs = $force ? [$runner->runNow($task, TaskRunner::SOURCE_MANUAL)] : $this->dueOnly($runner, $task);
            } catch (\InvalidArgumentException $e) {
                CLI::error($e->getMessage());

                return EXIT_ERROR;
            }
        } else {
            $runs = $runner->runDue();
        }

        if ($runs === []) {
            CLI::write(lang('Core.schedule.nothingDue'));

            return EXIT_SUCCESS;
        }

        $failed = false;

        foreach ($runs as $run) {
            $this->report($run);
            $failed = $failed || $run->status === TaskResult::FAILED;
        }

        return $failed ? EXIT_ERROR : EXIT_SUCCESS;
    }

    /**
     * @return list<TaskRun>
     */
    private function dueOnly(TaskRunner $runner, string $task): array
    {
        $runner->tasks()->sync(TaskRunner::now());
        $row = $runner->tasks()->find($task);

        if ($row === null || (int) $row['active'] !== 1) {
            throw new \InvalidArgumentException(lang('Core.schedule.unknownTask', [$task]));
        }

        return $runner->runDue(null, TaskRunner::SOURCE_CRON, (string) $row['class']);
    }

    private function report(TaskRun $run): void
    {
        $color = match ($run->status) {
            TaskResult::SUCCESS => 'green',
            TaskResult::FAILED  => 'red',
            default             => 'yellow',
        };

        CLI::write(CLI::color(lang('Core.schedule.status.' . $run->status), $color) . '  ' . $run->name . ' (' . $run->duration . ' ms)');

        if ($run->output !== '') {
            CLI::write('    ' . str_replace("\n", "\n    ", $run->output));
        }
    }
}
