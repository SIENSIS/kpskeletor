<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use DateTimeImmutable;
use Modules\Core\Notifications\Types\ScheduledTaskFailed;
use Modules\Core\Scheduler\ScheduledTasks;
use Modules\Core\Scheduler\TaskResult;
use Modules\Core\Scheduler\TaskRunner;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\LockedTask;
use Tests\Support\Scheduler\ProductionOnlyTask;
use Tests\Support\Scheduler\SuccessTask;
use Tests\Support\Scheduler\ThrowingTask;

/**
 * The scheduler: due runs and their records, missed runs, atomic claims, overlapping locks,
 * failures isolated and notified, disabled modules and other environments skipped, output.
 *
 * @internal
 */
final class SchedulerTest extends RbacTestCase
{
    use DeclaresFixtures;

    private const T0 = '2026-11-06 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        SuccessTask::$runs        = 0;
        LockedTask::$runs         = 0;
        ProductionOnlyTask::$runs = 0;
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    /**
     * @param list<class-string> $tasks
     */
    private function runner(array $tasks, string $module = 'Core'): TaskRunner
    {
        $this->declareFixtures(array_map(static fn (string $class): array => [$module, 'task', $class], $tasks), false);

        return new TaskRunner();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $class): array
    {
        $row = db_connect()->table('scheduled_tasks')->where('class', $class)->get()->getRowArray();
        $this->assertIsArray($row, $class);

        return $row;
    }

    public function testANewTaskWaitsForItsNextSlotThenRunsAndIsRecorded(): void
    {
        $runner = $this->runner([SuccessTask::class]);

        $this->assertSame([], $runner->runDue(self::at(self::T0)), 'Registered, first run at the next minute.');
        $this->assertSame('2026-11-06 10:01:00', $this->row(SuccessTask::class)['next_run_at']);

        $runs = $runner->runDue(self::at('2026-11-06 10:01:00'));
        $this->assertCount(1, $runs);
        $this->assertSame(TaskResult::SUCCESS, $runs[0]->status);
        $this->assertSame('Success fixture', $runs[0]->name);
        $this->assertSame(1, SuccessTask::$runs);

        $row = $this->row(SuccessTask::class);
        $this->assertSame('success', $row['last_status']);
        $this->assertSame('2026-11-06 10:01:00', $row['last_run_at']);
        $this->assertSame('2026-11-06 10:02:00', $row['next_run_at']);

        $record = db_connect()->table('task_runs')->where('task_id', $row['id'])->get()->getRowArray();
        $this->assertIsArray($record);
        $this->assertSame('success', $record['status']);
        $this->assertSame('cron', $record['source']);
        $this->assertStringContainsString('All good.', (string) $record['output']);
        $this->assertStringContainsString('printed by the task', (string) $record['output'], 'What the task prints is kept.');
    }

    public function testAMissedRunHappensOnceAndASlotIsNeverRunTwice(): void
    {
        $runner = $this->runner([SuccessTask::class]);
        $runner->runDue(self::at(self::T0));

        $this->assertCount(1, $runner->runDue(self::at('2026-11-06 10:30:00')), 'Cron was down for half an hour: one run.');
        $this->assertSame([], $runner->runDue(self::at('2026-11-06 10:30:00')), 'A second schedule:run in the same minute runs nothing.');
        $this->assertSame(1, SuccessTask::$runs);

        $row = $this->row(SuccessTask::class);
        $this->assertFalse((new ScheduledTasks())->claim((int) $row['id'], '2026-11-06 10:01:00', self::at('2026-11-06 10:31:00')), 'A stale claim fails.');
    }

    public function testAFailingTaskIsIsolatedLoggedAndNotifiedToTheSchedulersManagers(): void
    {
        $admin = $this->createUser('boss@example.com', 'admin');
        $root  = $this->createUser('root@example.com', 'superadmin');
        $user  = $this->createUser('ana@example.com', 'user');

        $runner = $this->runner([ThrowingTask::class, SuccessTask::class]);
        $runner->runDue(self::at(self::T0));
        $runs = $runner->runDue(self::at('2026-11-06 10:01:00'));

        $statuses = [];

        foreach ($runs as $run) {
            $statuses[$run->class] = $run->status;
        }

        $this->assertSame(TaskResult::FAILED, $statuses[ThrowingTask::class]);
        $this->assertSame(TaskResult::SUCCESS, $statuses[SuccessTask::class], 'The other task still ran.');
        $failed = $this->row(ThrowingTask::class);
        $this->assertSame('failed', $failed['last_status']);
        $record = db_connect()->table('task_runs')->where('task_id', $failed['id'])->get()->getRowArray();
        $this->assertIsArray($record);
        $this->assertStringContainsString('RuntimeException: Disk on fire', (string) $record['output']);
        $this->assertSame('2026-11-06 10:02:00', $this->row(ThrowingTask::class)['next_run_at'], 'A failure does not block the next run.');

        $notified = array_map(intval(...), array_column(db_connect()->table('notifications')->where('type', ScheduledTaskFailed::class)->get()->getResultArray(), 'user_id'));
        sort($notified);
        $this->assertSame([(int) $admin->id, (int) $root->id], $notified, 'Holders of schedule.list are told; others are not.');
        $this->assertNotContains((int) $user->id, $notified);
    }

    public function testWithoutOverlappingSkipsWhileTheLockIsHeldAndReleasesItAfterwards(): void
    {
        $runner = $this->runner([LockedTask::class]);
        $runner->runDue(self::at(self::T0));
        $id    = (int) $this->row(LockedTask::class)['id'];
        $tasks = new ScheduledTasks();

        $this->assertTrue($tasks->lock($id, 'another-process', self::at(self::T0), 10));
        $run = $runner->runNow(LockedTask::class, TaskRunner::SOURCE_MANUAL, null, self::at('2026-11-06 10:05:00'));
        $this->assertSame(TaskResult::SKIPPED, $run->status);
        $this->assertSame(0, LockedTask::$runs);
        $this->assertNull($this->row(LockedTask::class)['last_status'], 'A skipped run is not the last result.');

        $run = $runner->runNow(LockedTask::class, TaskRunner::SOURCE_MANUAL, null, self::at('2026-11-06 10:11:00'));
        $this->assertSame(TaskResult::SUCCESS, $run->status, 'An expired lock (dead process) is taken over.');
        $this->assertSame(1, LockedTask::$runs);
        $this->assertNull($this->row(LockedTask::class)['lock_owner'], 'The lock is released after the run.');

        $this->assertSame(TaskResult::SUCCESS, $runner->runNow(LockedTask::class, TaskRunner::SOURCE_MANUAL, null, self::at('2026-11-06 10:11:00'))->status);
    }

    public function testTasksOfDisabledModulesDoNotRun(): void
    {
        $runner = $this->runner([SuccessTask::class], 'News');
        $runner->runDue(self::at(self::T0));
        $this->setModuleEnabled('News', false);
        $this->forgetDeclarations();

        $this->assertSame([], $runner->runDue(self::at('2026-11-06 10:05:00')));
        $this->assertSame('0', (string) $this->row(SuccessTask::class)['active']);
        $this->assertSame(0, SuccessTask::$runs);

        $this->expectException(\InvalidArgumentException::class);
        $runner->runNow(SuccessTask::class);
    }

    public function testTasksLimitedToAnotherEnvironmentDoNotRun(): void
    {
        $runner = $this->runner([ProductionOnlyTask::class]);
        $runner->runDue(self::at(self::T0));

        $this->assertSame([], $runner->runDue(self::at('2026-11-06 10:01:00')));
        $this->assertSame(0, ProductionOnlyTask::$runs);
        $this->assertSame('2026-11-06 10:02:00', $this->row(ProductionOnlyTask::class)['next_run_at']);

        $production = new TaskRunner(new ScheduledTasks(), null, 'production');
        $this->assertCount(1, $production->runDue(self::at('2026-11-06 10:02:00')));
    }

    public function testRunNowFindsTasksByShortNameAndRecordsTheUser(): void
    {
        $admin  = $this->createUser('boss@example.com', 'admin');
        $runner = $this->runner([SuccessTask::class]);

        $run = $runner->runNow('successtask', TaskRunner::SOURCE_MANUAL, (int) $admin->id);
        $this->assertSame(TaskResult::SUCCESS, $run->status);

        $record = db_connect()->table('task_runs')->orderBy('id', 'desc')->get()->getRowArray();
        $this->assertIsArray($record);
        $this->assertSame('manual', $record['source']);
        $this->assertSame((int) $admin->id, (int) $record['user_id']);
    }

    public function testOnlyTheLatestRunsAreKept(): void
    {
        $runner = $this->runner([SuccessTask::class]);

        for ($i = 0; $i < TaskRunner::KEEP_RUNS + 5; $i++) {
            $runner->runNow(SuccessTask::class);
        }

        $this->assertSame(TaskRunner::KEEP_RUNS, db_connect()->table('task_runs')->countAllResults());
    }

    public function testRemovedTasksBecomeInactiveAndChangedSchedulesAreRecalculated(): void
    {
        $runner = $this->runner([SuccessTask::class, LockedTask::class]);
        $runner->runDue(self::at(self::T0));
        db_connect()->table('scheduled_tasks')->where('class', LockedTask::class)->update(['expression' => '0 4 * * *']);

        $this->declareFixtures([['Core', 'task', LockedTask::class]], false);
        (new ScheduledTasks())->sync(self::at(self::T0));

        $this->assertSame('0', (string) $this->row(SuccessTask::class)['active']);
        $this->assertSame('0 3 * * *', $this->row(LockedTask::class)['expression']);
        $this->assertSame('2026-11-07 03:00:00', $this->row(LockedTask::class)['next_run_at']);
    }
}
