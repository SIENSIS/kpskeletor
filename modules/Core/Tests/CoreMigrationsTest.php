<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Modules\ModuleMigrator;

/**
 * Migrations of the Core: up → down → up leaves the same tables.
 *
 * @internal
 */
final class CoreMigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
    }

    private const TABLES = [
        'modules', 'permissions', 'group_permissions', 'module_declarations', 'demo_records',
        'notifications', 'notification_preferences', 'mail_queue', 'scheduled_tasks', 'task_runs',
        'activity_log', 'stored_files', 'groups',
    ];

    private const MIGRATIONS = [
        '2026-11-04-000001_CreateModules',
        '2026-11-04-000002_CreatePermissions',
        '2026-11-04-000003_CreateGroupPermissions',
        '2026-11-04-000004_AddGrantsAppliedToModules',
        '2026-11-04-000005_CreateModuleDeclarations',
        '2026-11-04-000006_CreateDemoRecords',
        '2026-11-06-000001_CreateNotifications',
        '2026-11-06-000002_CreateNotificationPreferences',
        '2026-11-06-000003_CreateMailQueue',
        '2026-11-06-000004_CreateScheduledTasks',
        '2026-11-06-000005_CreateTaskRuns',
        '2026-11-07-000001_CreateActivityLog',
        '2026-11-08-000001_CreateStoredFiles',
        '2026-11-09-000001_CreateGroups',
    ];

    public function testUpDownUp(): void
    {
        $migrator = new ModuleMigrator();
        $count    = count(self::MIGRATIONS);

        $this->assertSame($count, $migrator->migrate('Modules\Core'));
        $this->assertTablesExist(true);
        $this->assertTrue($this->db->fieldExists('grants_applied', 'modules'));
        $this->assertSame([], $migrator->pending('Modules\Core'));

        $this->assertSame($count, $migrator->rollback('Modules\Core'));
        $this->assertTablesExist(false);
        $this->assertSame(self::MIGRATIONS, $migrator->pending('Modules\Core'));

        $this->assertSame($count, $migrator->migrate('Modules\Core'));
        $this->assertTablesExist(true);
    }

    private function assertTablesExist(bool $exist): void
    {
        $this->db->resetDataCache();

        foreach (self::TABLES as $table) {
            $this->assertSame($exist, $this->db->tableExists($table), $table);
        }
    }

    public function testPackageMigrationsAreNeverRolledBack(): void
    {
        $this->expectException(\LogicException::class);

        (new ModuleMigrator())->rollback('CodeIgniter\Shield');
    }
}
