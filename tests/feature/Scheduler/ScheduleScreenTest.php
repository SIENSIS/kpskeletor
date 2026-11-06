<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Kpcrudy\Crud;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Permissions\GrantManager;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\SuccessTask;

/**
 * `schedule:run` / `schedule:list` and the scheduled tasks screen (resource `schedule`): a task
 * run from the command shows its last run and output in the back office; "run now" needs
 * `schedule.run`, also against forged requests.
 *
 * @internal
 */
final class ScheduleScreenTest extends AuthTestCase
{
    use DeclaresFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        SuccessTask::$runs = 0;
        $this->declareFixtures([['Core', 'task', SuccessTask::class]]);
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('ana@example.com', 'user');
    }

    protected function tearDown(): void
    {
        CLI::resetInputOutput();
        parent::tearDown();
    }

    private function spark(string $command): string
    {
        $io = new MockInputOutput();
        CLI::setInputOutput($io);
        command($command);
        CLI::resetInputOutput();

        return $io->getOutput();
    }

    private function taskId(): int
    {
        $row = db_connect()->table('scheduled_tasks')->where('class', SuccessTask::class)->get()->getRowArray();
        $this->assertIsArray($row);

        return (int) $row['id'];
    }

    public function testATaskRunFromTheCommandShowsItsLastRunInTheScreen(): void
    {
        $output = $this->spark('schedule:run --task=SuccessTask --force');

        $this->assertStringContainsString('Success fixture', (string) $output);
        $this->assertSame(1, SuccessTask::$runs);

        $list = $this->spark('schedule:list');
        $this->assertStringContainsString('Success fixture', (string) $list);
        $this->assertStringContainsString('(Success)', (string) $list);

        $this->login('boss@example.com');
        $page = $this->send('GET', 'backend/schedule');
        $page->assertOK();
        $page->assertSee('Success fixture');
        $page->assertSee('Send the queued notification emails');
        $page->assertSee('Success');

        $show = $this->send('GET', 'backend/schedule/show/' . Crud::idForUrl($this->taskId(), 'scheduled_tasks'));
        $show->assertOK();
        $show->assertSee('All good.');
        $show->assertSee('printed by the task');
        $show->assertSee('Run now');
    }

    public function testRunNowFromTheScreen(): void
    {
        $this->login('boss@example.com');
        $this->send('GET', 'backend/schedule');

        $response = $this->send('POST', 'backend/schedule/run/' . Crud::idForUrl($this->taskId(), 'scheduled_tasks'));
        $response->assertRedirect();
        $this->assertSame(1, SuccessTask::$runs);

        $run = db_connect()->table('task_runs')->where('task_id', $this->taskId())->get()->getRowArray();
        $this->assertIsArray($run);
        $this->assertSame('manual', $run['source']);
    }

    public function testRunNowNeedsItsPermissionEvenWhenForged(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['schedule.list', 'schedule.show']);
        $this->syncPermissions();
        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');

        $page = $this->send('GET', 'backend/schedule');
        $page->assertOK();
        $page->assertDontSee('/backend/schedule/run/');

        $this->send('POST', 'backend/schedule/run/' . Crud::idForUrl($this->taskId(), 'scheduled_tasks'));
        $this->assertSame(0, SuccessTask::$runs);
    }

    public function testUsersWithoutTheScheduleAreRefused(): void
    {
        $this->login('ana@example.com');
        $this->send('GET', 'backend/schedule')->assertStatus(403);
        $this->send('POST', 'backend/schedule/run/1')->assertStatus(403);
        $this->assertSame(0, SuccessTask::$runs);
    }

    public function testTheCommandRefusesUnknownTasks(): void
    {
        $this->assertStringContainsString('does not exist', $this->spark('schedule:run --task=Nope --force'));
        $this->assertStringContainsString('--force needs', $this->spark('schedule:run --force'));
        $this->assertSame(0, SuccessTask::$runs);
    }
}
