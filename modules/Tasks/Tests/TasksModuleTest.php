<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Core\Modules\ManifestValidator;
use Modules\Tasks\Calendar\DueDates;
use Modules\Tasks\Config\Module;
use Modules\Tasks\Notifications\TaskAssigned;
use Modules\Tasks\Services\TaskRepository;

/**
 * The Tasks module (tutorials 1, 2 and 14): manifest, the to-do task CRUD with its permissions
 * (forged POSTs included), the `complete` operation and bulk action, the hidden estimate, the
 * `afterWrite()` hook (calendar, notifications) and the migrations up/down/up.
 *
 * @internal
 */
final class TasksModuleTest extends TasksTestCase
{
    public function testManifestIsValidAndTheModuleIsInstalledDisabled(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertFalse($manifest->enabledByDefault, 'Tutorial module: disabled until enabled in Modules (D-32-01).');
        $this->assertFileExists(dirname(__DIR__) . '/CLAUDE.md');
    }

    public function testScreensNeedTheirPermissions(): void
    {
        $this->send('GET', 'backend/tasks/tasks')->assertRedirectTo(site_url('login'));

        $this->loginWith();
        $this->send('GET', 'backend/tasks/tasks')->assertStatus(403);
        $this->send('GET', 'backend/tasks/projects')->assertStatus(403);
        $this->send('GET', 'backend/tasks/requests')->assertStatus(403);
    }

    public function testEveryGroupHasTheBoardAndAdminsEverything(): void
    {
        $user = $this->createUser('pau@example.com', 'user');
        $this->login('pau@example.com');
        $this->send('GET', 'backend/tasks')->assertOK();
        $this->send('GET', 'backend/tasks/tasks')->assertStatus(403);

        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        foreach (['backend/tasks', 'backend/tasks/tasks', 'backend/tasks/projects', 'backend/tasks/requests'] as $path) {
            $this->send('GET', $path)->assertOK();
        }

        $this->assertNotNull($user->id);
    }

    public function testAReadOnlyUserCannotWriteEvenWithForgedPosts(): void
    {
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show');
        $id   = $this->task('Revisar', ['status' => 'todo']);
        $code = $this->taskCode($id);
        $list = 'backend/tasks/tasks';
        $rows = db_connect()->table('tasks')->countAllResults();

        $this->send('GET', $list)->assertOK();
        $this->assertCrudRefused($this->send('POST', $list . '/add', ['title' => 'Nova']), $list, 'add');
        $this->assertCrudRefused($this->send('POST', $list . '/edit/' . $code, ['title' => 'Canviada']), $list, 'edit');
        $this->assertCrudRefused($this->send('POST', $list . '/delete/' . $code), $list, 'delete');
        $this->assertCrudRefused($this->send('POST', $list . '/complete/' . $code), $list, 'complete');
        $this->assertCrudRefused($this->send('POST', $list . '/bulk', ['action' => 'complete', 'ids' => [$code]]), $list, 'bulk complete');

        $this->assertSame($rows, db_connect()->table('tasks')->countAllResults());
        $this->assertSame('Revisar', $this->taskRow($id)['title']);
        $this->assertSame('todo', $this->taskRow($id)['status']);
        $this->assertNull($this->taskRow($id)['deleted_at']);
    }

    public function testCompleteNeedsItsOwnPermission(): void
    {
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.edit');
        $id   = $this->task('Revisar');
        $list = 'backend/tasks/tasks';

        $this->assertCrudRefused($this->send('POST', $list . '/complete/' . $this->taskCode($id)), $list, 'complete without tasks-tasks.complete');
        $this->assertSame('todo', $this->taskRow($id)['status']);
    }

    public function testTheCompleteOperationAndBulkAction(): void
    {
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.complete');
        $one   = $this->task('Una');
        $two   = $this->task('Dues');
        $three = $this->task('Tres');

        $this->send('POST', 'backend/tasks/tasks/complete/' . $this->taskCode($one))->assertRedirect();
        $this->assertSame('done', $this->taskRow($one)['status']);
        $this->assertNotNull($this->taskRow($one)['completed_at']);

        $this->send('POST', 'backend/tasks/tasks/bulk', ['action' => 'complete', 'ids' => [$this->taskCode($two), $this->taskCode($three)]])->assertRedirect();
        $this->assertSame('done', $this->taskRow($two)['status']);
        $this->assertSame('done', $this->taskRow($three)['status']);
        $actions = array_column(db_connect()->table('activity_log')->select('action')->where('module', 'Tasks')->get()->getResultArray(), 'action');
        $this->assertContains('complete', $actions, 'Operations are logged by the Core (AuditedCrud).');
    }

    public function testTheEstimateIsHiddenWithoutItsPermission(): void
    {
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.add', 'tasks-tasks.edit');
        $id = $this->task('Amb estimació', ['estimate_hours' => '8.00']);

        $this->assertStringNotContainsString('estimate_hours', $this->body('GET', 'backend/tasks/tasks/edit/' . $this->taskCode($id)));
        $this->assertStringNotContainsString(lang('Tasks.fields.estimate_hours'), $this->body('GET', 'backend/tasks/tasks/show/' . $this->taskCode($id)));

        // A forged estimate is not saved.
        $this->send('POST', 'backend/tasks/tasks/edit/' . $this->taskCode($id), $this->form('Amb estimació', ['estimate_hours' => '99']))->assertRedirect();
        $this->assertSame('8.00', number_format((float) $this->taskRow($id)['estimate_hours'], 2, '.', ''));
    }

    public function testTheEstimateIsShownWithItsPermission(): void
    {
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.edit', 'tasks-tasks.estimates');
        $id = $this->task('Amb estimació', ['estimate_hours' => '8.00']);

        $this->assertStringContainsString('estimate_hours', $this->body('GET', 'backend/tasks/tasks/edit/' . $this->taskCode($id)));
        $this->send('POST', 'backend/tasks/tasks/edit/' . $this->taskCode($id), $this->form('Amb estimació', ['estimate_hours' => '5.5']))->assertRedirect();
        $this->assertSame('5.50', number_format((float) $this->taskRow($id)['estimate_hours'], 2, '.', ''));
    }

    public function testAddingAToDoTaskSetsTheCreatorNotifiesAssigneesAndPublishesTheDueDate(): void
    {
        $boss  = $this->createUser('boss@example.com', 'admin');
        $laia  = $this->createUser('laia@example.com', 'editor');
        $this->login('boss@example.com');
        $due   = date('Y-m-d', strtotime('+3 days'));

        $this->send('POST', 'backend/tasks/tasks/add', $this->form('Preparar el cartell', [
            'due_on'    => $due,
            'labels'    => ['design', 'unknown'],
            'assignees' => [(string) $laia->id, (string) $boss->id],
        ]))->assertRedirect();

        $row = db_connect()->table('tasks')->where('title', 'Preparar el cartell')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame((int) $boss->id, (int) $row['created_by']);
        $this->assertSame(['design'], json_decode((string) $row['labels'], true), 'Multiselect: only known labels.');

        $this->assertSame(1, $this->notificationsOf((int) $laia->id, TaskAssigned::class), 'The new assignee is notified.');
        $this->assertSame(0, $this->notificationsOf((int) $boss->id, TaskAssigned::class), 'Not the user who assigned it.');

        $event = db_connect()->table('events')->where('source_module', 'Tasks')->where('source_key', DueDates::key((int) $row['id']))->get()->getRowArray();
        $this->assertIsArray($event, 'The due date is in the calendar tasks.due.');
        $this->assertSame('tasks.due', $event['calendar']);
        $this->assertSame('restricted', $event['visibility']);

        // Editing without changing the assignees notifies nobody again; completing it removes the event.
        $code = $this->taskCode((int) $row['id']);
        $this->send('POST', 'backend/tasks/tasks/edit/' . $code, $this->form('Preparar el cartell', [
            'due_on' => $due, 'status' => 'done', 'assignees' => [(string) $laia->id, (string) $boss->id],
        ]))->assertRedirect();
        $this->assertSame(1, $this->notificationsOf((int) $laia->id, TaskAssigned::class));
        $this->assertNotNull($this->taskRow((int) $row['id'])['completed_at']);
        $this->assertNotNull(db_connect()->table('events')->where('source_key', DueDates::key((int) $row['id']))->get()->getRowArray()['deleted_at']);
    }

    public function testProjectsHaveTranslatedNamesAndAUniqueSlug(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $post = static fn (string $ca, string $es = ''): array => [
            'kp_tr' => ['ca' => ['name' => $ca, 'description' => ''], 'es' => ['name' => $es, 'description' => ''], 'en' => ['name' => '', 'description' => '']],
            'slug' => '', 'color' => '#198754', 'ends_on' => '', 'active' => '1',
        ];

        $this->send('POST', 'backend/tasks/projects/add', $post('Nou web', 'Nueva web'))->assertRedirect();
        $this->send('POST', 'backend/tasks/projects/add', $post('Nou web'))->assertRedirect();

        $slugs = array_column(db_connect()->table('task_projects')->orderBy('id')->get()->getResultArray(), 'slug');
        $this->assertSame(['nou-web', 'nou-web-2'], $slugs);
        $this->assertSame(3, db_connect()->table('task_project_translations')->countAllResults(), 'Only filled-in languages are stored.');

        $this->send('POST', 'backend/tasks/projects/add', $post(''))->assertOK();
        $this->assertSame(2, db_connect()->table('task_projects')->countAllResults(), 'The Catalan name is required.');
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];

        foreach ($files as $file) {
            $class = 'Modules\Tasks\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(7, $migrations, 'Six tables (0.1.0) and the started_at column (0.2.0, tutorial 13).');

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();

        foreach (['task_projects', 'task_project_translations', 'tasks', 'task_assignees', 'task_attachments', 'task_requests'] as $table) {
            $this->assertFalse($this->db->tableExists($table), $table . ' dropped.');
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach (['task_projects', 'task_project_translations', 'tasks', 'task_assignees', 'task_attachments', 'task_requests'] as $table) {
            $this->assertTrue($this->db->tableExists($table), $table . ' created again.');
        }

        $this->assertTrue($this->db->fieldExists('started_at', 'tasks'), 'The 0.2.0 migration added started_at again.');
    }

    public function testTheVersionMigrationAddsStartedAtFillsItAndGoesBack(): void
    {
        $file = dirname(__DIR__) . '/Database/Migrations/2026-11-18-100001_AddStartedAtToTasks.php';
        $this->assertFileExists($file);
        require_once $file;

        $migration = new \Modules\Tasks\Database\Migrations\AddStartedAtToTasks(Database::forge('tests'));
        $this->assertSame('0.2.0', (new Module())->version, 'The schema change comes with a new version (tutorial 13).');

        $todo  = $this->task('Pendent', ['status' => 'todo']);
        $doing = $this->task('En curs', ['status' => 'doing', 'updated_at' => '2026-11-10 09:00:00']);

        $migration->down();
        $this->db->resetDataCache();
        $this->assertFalse($this->db->fieldExists('started_at', 'tasks'), 'down() drops the column.');
        $this->assertSame('En curs', $this->taskRow($doing)['title'], 'Dropping the column keeps the rows.');

        $migration->up();
        $this->db->resetDataCache();
        $this->assertTrue($this->db->fieldExists('started_at', 'tasks'));
        $this->assertNull($this->taskRow($todo)['started_at'], 'A to-do task not started has no date.');
        $this->assertSame('2026-11-10 09:00:00', $this->taskRow($doing)['started_at'], 'Started to-do tasks take their last change.');
    }

    public function testStartedAtAndCompletedAtFollowTheStatus(): void
    {
        $now = \CodeIgniter\I18n\Time::parse('2026-11-18 10:00:00');

        $this->assertSame(['started_at' => null, 'completed_at' => null], TaskRepository::statusDates('todo', [], $now));
        $this->assertSame(['started_at' => '2026-11-18 10:00:00', 'completed_at' => null], TaskRepository::statusDates('doing', ['status' => 'todo', 'started_at' => null], $now));
        $this->assertSame(['completed_at' => '2026-11-18 10:00:00'], TaskRepository::statusDates('done', ['status' => 'doing', 'started_at' => '2026-11-17 08:00:00'], $now), 'The start is kept when it is done.');
        $this->assertSame([], TaskRepository::statusDates('done', ['status' => 'done', 'started_at' => '2026-11-17 08:00:00', 'completed_at' => '2026-11-17 18:00:00'], $now), 'Nothing changes while it stays done.');
        $this->assertSame(['completed_at' => null], TaskRepository::statusDates('doing', ['status' => 'done', 'started_at' => '2026-11-17 08:00:00'], $now), 'Reopened: not completed, still started.');

        // The CRUD and the board use the same rule.
        $this->loginWith('tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.edit', 'tasks-tasks.complete');
        $id = $this->task('Revisar');
        $this->send('POST', 'backend/tasks/tasks/edit/' . $this->taskCode($id), $this->form('Revisar', ['status' => 'doing']))->assertRedirect();
        $this->assertNotNull($this->taskRow($id)['started_at']);
        $this->assertNull($this->taskRow($id)['completed_at']);

        $this->send('POST', 'backend/tasks/tasks/complete/' . $this->taskCode($id))->assertRedirect();
        $this->assertNotNull($this->taskRow($id)['started_at']);
        $this->assertNotNull($this->taskRow($id)['completed_at']);

        $this->send('POST', 'backend/tasks/tasks/edit/' . $this->taskCode($id), $this->form('Revisar', ['status' => 'todo']))->assertRedirect();
        $this->assertNull($this->taskRow($id)['started_at']);
        $this->assertNull($this->taskRow($id)['completed_at']);
    }

    /**
     * The fields of the to-do task form.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function form(string $title, array $values = []): array
    {
        return $values + [
            'title' => $title, 'description' => '', 'project_id' => '', 'status' => 'todo', 'priority' => 'normal',
            'labels' => [], 'due_on' => '', 'assignees' => [], 'visibility' => 'private', 'attachments' => '',
        ];
    }
}
