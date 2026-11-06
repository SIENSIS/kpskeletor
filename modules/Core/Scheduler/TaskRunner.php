<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Notifications\Types\ScheduledTaskFailed;
use Throwable;

/**
 * Runs the scheduled tasks (`php spark schedule:run`, "run now" in the back office and the poor
 * man's cron).
 *
 * - A task is due when its `next_run_at` has passed; the runner moves `next_run_at` forward in an
 *   atomic update before running it, so two `schedule:run` processes never run the same slot
 *   twice, and a run missed while cron was down happens once on the next call.
 * - `withoutOverlapping()` tasks take a database lock (works on shared hosting); a run that finds
 *   the lock taken is recorded as skipped.
 * - Every run is recorded in `task_runs` (latest KEEP_RUNS per task) with its duration, result,
 *   output and source; a failure (a `failed` result or an exception) is logged and notified to
 *   the holders of `schedule.list`, and the other tasks still run.
 * - Tasks of disabled modules, and tasks limited to other environments, do not run.
 */
final class TaskRunner
{
    public const SOURCE_CRON   = 'cron';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_WEB    = 'web';

    /** Runs kept per task. */
    public const KEEP_RUNS = 50;

    public function __construct(
        private readonly ScheduledTasks $tasks = new ScheduledTasks(),
        private readonly ?Notifier $notifier = null,
        private readonly string $environment = ENVIRONMENT,
    ) {
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
    }

    public function tasks(): ScheduledTasks
    {
        return $this->tasks;
    }

    /**
     * Runs every task that is due (or only `$onlyClass`, if it is due).
     *
     * @return list<TaskRun>
     */
    public function runDue(?DateTimeImmutable $now = null, string $source = self::SOURCE_CRON, ?string $onlyClass = null): array
    {
        $now ??= self::now();
        $this->tasks->sync($now);
        $runs  = [];
        $stamp = $now->format(ScheduledTasks::FORMAT);

        foreach ($this->tasks->rows() as $row) {
            if ($onlyClass !== null && $row['class'] !== $onlyClass) {
                continue;
            }

            if ((int) $row['active'] !== 1 || $row['next_run_at'] === null || (string) $row['next_run_at'] > $stamp) {
                continue;
            }

            $task = $this->tasks->instance((string) $row['class']);

            if ($task === null) {
                continue;
            }

            $schedule = $task->schedule();

            if (! $this->tasks->claim((int) $row['id'], (string) $row['next_run_at'], $schedule->nextRun($now))) {
                continue; // another process took this run
            }

            if (! $schedule->runsInEnvironment($this->environment)) {
                continue;
            }

            $runs[] = $this->execute($row, $task, $source, null, $now);
        }

        return $runs;
    }

    /**
     * Runs one task now, due or not ("run now", `schedule:run --task=… --force`). Inactive tasks
     * (disabled module) are refused; the `withoutOverlapping()` lock still applies.
     *
     * @param int|string $task Id, class or short class name
     *
     * @throws \InvalidArgumentException for an unknown or inactive task
     */
    public function runNow(int|string $task, string $source = self::SOURCE_MANUAL, ?int $userId = null, ?DateTimeImmutable $now = null): TaskRun
    {
        $now ??= self::now();
        $this->tasks->sync($now);
        $row = $this->tasks->find($task);

        if ($row === null || (int) $row['active'] !== 1 || ($instance = $this->tasks->instance((string) $row['class'])) === null) {
            throw new \InvalidArgumentException(lang('Core.schedule.unknownTask', [(string) $task]));
        }

        return $this->execute($row, $instance, $source, $userId, $now);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function execute(array $row, TaskInterface $task, string $source, ?int $userId, DateTimeImmutable $now): TaskRun
    {
        $id       = (int) $row['id'];
        $schedule = $task->schedule();
        $owner    = bin2hex(random_bytes(16));
        $locked   = false;

        if ($schedule->preventsOverlapping()) {
            $locked = $this->tasks->lock($id, $owner, $now, (int) $schedule->lockMinutes());

            if (! $locked) {
                return $this->record($row, $task, TaskResult::skipped(lang('Core.schedule.stillRunning')), 0, $source, $userId, $now);
            }
        }

        $started = hrtime(true);
        $level   = ob_get_level();
        ob_start();

        try {
            $result = $task->run();
        } catch (Throwable $e) {
            $result = TaskResult::failure($e::class . ': ' . $e->getMessage());
        } finally {
            $printed = '';

            while (ob_get_level() > $level) {
                $printed = (string) ob_get_clean() . $printed;
            }

            if ($locked) {
                $this->tasks->unlock($id, $owner);
            }
        }

        $duration = (int) round((hrtime(true) - $started) / 1_000_000);

        return $this->record($row, $task, $result->withOutput($printed), $duration, $source, $userId, $now);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function record(array $row, TaskInterface $task, TaskResult $result, int $duration, string $source, ?int $userId, DateTimeImmutable $now): TaskRun
    {
        $id       = (int) $row['id'];
        $db       = $this->tasks->db();
        $finished = self::now();

        $db->table('task_runs')->insert([
            'task_id'     => $id,
            'started_at'  => $now->format(ScheduledTasks::FORMAT),
            'finished_at' => $finished->format(ScheduledTasks::FORMAT),
            'duration'    => $duration,
            'status'      => $result->status,
            'output'      => $result->storedOutput(),
            'source'      => $source,
            'user_id'     => $userId,
        ]);

        if ($result->status !== TaskResult::SKIPPED) {
            $db->table('scheduled_tasks')->where('id', $id)->update([
                'last_run_at'   => $now->format(ScheduledTasks::FORMAT),
                'last_status'   => $result->status,
                'last_duration' => $duration,
            ]);
        }

        // Keep the latest runs only.
        $keep = $db->table('task_runs')->select('id')->where('task_id', $id)->orderBy('id', 'desc')->limit(1, self::KEEP_RUNS - 1)->get()->getRowArray();

        if ($keep !== null) {
            $db->table('task_runs')->where('task_id', $id)->where('id <', (int) $keep['id'])->delete();
        }

        $run = new TaskRun((string) $row['class'], $this->nameOf($task), $result->status, $result->storedOutput(), $duration);

        if ($result->failed()) {
            log_message('error', 'Scheduled task {class} failed: {output}', ['class' => $row['class'], 'output' => $result->storedOutput()]);
            $this->notifyFailure($row, $run);
        }

        return $run;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function notifyFailure(array $row, TaskRun $run): void
    {
        try {
            ($this->notifier ?? service('notifications'))->toPermission('schedule.list', new ScheduledTaskFailed([
                'id'     => (int) $row['id'],
                'class'  => $run->class,
                'output' => mb_substr($run->output, 0, 500),
            ]));
        } catch (Throwable $e) {
            log_message('error', 'The failure of the scheduled task {class} could not be notified: {message}', ['class' => $run->class, 'message' => $e->getMessage()]);
        }
    }

    private function nameOf(TaskInterface $task): string
    {
        try {
            return $task->name();
        } catch (Throwable) {
            return $task::class;
        }
    }
}
