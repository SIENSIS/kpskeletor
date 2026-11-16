<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Backup\Config\Module;
use Modules\Backup\Database\Migrations\CreateBackups;
use Modules\Backup\Services\JobStore;
use Modules\Backup\Services\Retention;
use Modules\Backup\Tasks\PruneBackups;
use Modules\Backup\Tasks\ScheduledBackup;
use Modules\Backup\Widgets\Backups;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Scheduler\TaskResult;

/**
 * The manifest and default grants, the migration (up, down, up), retention, the scheduled
 * tasks with their notifications, the widget, the job files and the console commands.
 *
 * @internal
 */
final class BackupModuleTest extends BackupTestCase
{
    use StreamFilterTrait;

    protected function tearDown(): void
    {
        putenv('BACKUP_PASSWORD');
        unset($_ENV['BACKUP_PASSWORD']);
        service('superglobals')->unsetServer('BACKUP_PASSWORD');
        Time::setTestNow();
        parent::tearDown();
    }

    public function testManifestAndGrants(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertSame('backup', $manifest->permissionPrefix('backups'));
        $this->assertSame(['admin' => ['backup.list', 'backup.show', 'backup.create']], $manifest->defaultGrants, 'Download, delete and restore stay with the superadmin.');
    }

    public function testMigrationUpDownUp(): void
    {
        $migration = new CreateBackups(\Config\Database::forge('tests'));
        $migration->down();
        $this->db->resetDataCache();
        $this->assertFalse($this->db->tableExists('backups'));
        $migration->up();
        $this->db->resetDataCache();
        $this->assertTrue($this->db->tableExists('backups'));
    }

    public function testRetentionKeepsTheNewestOfEachDayWeekAndMonth(): void
    {
        $now     = Time::parse('2026-11-16 12:00:00', 'UTC');
        $backups = [];

        // Two backups a day for 120 days.
        for ($day = 0; $day < 120; $day++) {
            foreach (['10:00:00', '20:00:00'] as $time) {
                $at        = $now->subDays($day)->format('Y-m-d') . ' ' . $time;
                $backups[] = ['file' => 'b-' . $at, 'created_at' => $at];
            }
        }

        $backups[] = ['file' => 'recent', 'created_at' => $now->subMinutes(10)->format('Y-m-d H:i:s')];
        $delete    = Retention::select($backups, 7, 4, 3, $now);
        $kept      = array_values(array_diff(array_column($backups, 'file'), $delete));

        $this->assertContains('recent', $kept, 'Never a backup younger than an hour.');
        $this->assertContains('b-2026-11-16 10:00:00', $delete, 'Today\'s newest is the recent one.');
        $this->assertContains('b-2026-11-15 20:00:00', $kept);
        $this->assertNotContains('b-2026-11-15 10:00:00', $kept);
        $this->assertContains('b-2026-11-10 20:00:00', $kept, 'Seventh day.');
        $this->assertNotContains('b-2026-11-09 20:00:00', $kept, 'Eighth day: not the newest of its week nor month.');
        $this->assertContains('b-2026-10-31 20:00:00', $kept, 'Newest of October.');
        $this->assertContains('b-2026-09-30 20:00:00', $kept, 'Newest of September.');
        $this->assertNotContains('b-2026-08-31 20:00:00', $kept, 'Fourth month.');
        $this->assertLessThanOrEqual(7 + 4 + 3 + 1, count($kept));
        $this->assertSame([], Retention::select($backups, 0, 0, 0, $now), 'All tiers at 0 keep everything.');
    }

    public function testPruneDeletesFilesAndRows(): void
    {
        $repository = $this->service()->repository();
        $old        = [];

        foreach (['2026-01-05 10:00:00', '2026-01-06 10:00:00', '2026-01-07 10:00:00'] as $at) {
            $name = $repository->newName('full', Time::parse($at, 'UTC'));
            file_put_contents($repository->folder() . '/' . $name, 'x');
            $repository->record(['file' => $name, 'created_at' => $at]);
            $old[] = $name;
        }

        setting('Backup.keepDaily', 1);
        setting('Backup.keepWeekly', 0);
        setting('Backup.keepMonthly', 0);

        $this->assertSame(TaskResult::SUCCESS, (new PruneBackups())->run()->status);

        $this->assertSame([$old[2]], $repository->files());
        $this->assertNull($repository->findByFile($old[0]));
        $this->assertCount(1, $this->entries('prune'));
    }

    public function testScheduledBackupNeedsTheSettingAndThePasswordAndNotifies(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $task  = new ScheduledBackup();

        $this->assertSame(TaskResult::SKIPPED, $task->run()->status, 'Off by default.');

        setting('Backup.scheduled', true);
        $result = $task->run();
        $this->assertSame(TaskResult::FAILED, $result->status);
        $this->assertSame(lang('Backup.error.noPassword'), $result->output);
        $this->assertSame(1, $this->notificationsOf((int) $admin->id));

        putenv('BACKUP_PASSWORD=' . self::PASSWORD);
        $this->assertSame(TaskResult::SUCCESS, $task->run()->status);
        $this->assertCount(1, $this->service()->repository()->files());
        $this->assertSame('schedule', $this->service()->repository()->successful()[0]['source']);
        $this->assertSame(2, $this->notificationsOf((int) $admin->id));
    }

    public function testWidget(): void
    {
        $widget = new Backups();
        $this->assertSame('backup.list', $widget->permission());
        $this->assertStringContainsString(lang('Backup.widget.none'), $widget->render([]));

        $this->service()->create(self::PASSWORD);
        $html = $widget->render([]);
        $this->assertStringContainsString(lang('Backup.widget.lastOk'), $html);
        $this->assertStringContainsString(lang('Backup.widget.notScheduled'), $html);
    }

    public function testJobFilesArePrivateAndPurged(): void
    {
        $jobs = new JobStore($this->config);
        $id   = $jobs->create('restore', 1, ['file' => 'x']);
        $jobs->putEnv($id, 'secret');

        $this->assertMatchesRegularExpression(JobStore::ID, $id);
        $this->assertSame(0600, fileperms($this->config->path . "/jobs/{$id}.json") & 0777);
        $this->assertSame(0600, fileperms($this->config->path . "/jobs/{$id}.env") & 0777);
        $this->assertNull($jobs->get('../' . $id));
        $this->assertSame(0, $jobs->purge());
        $this->assertSame(2, $jobs->purge(Time::now()->addHours(25)));
        $this->assertNull($jobs->get($id));
    }

    public function testCommands(): void
    {
        command('backup:create');
        $this->assertStringContainsString(lang('Backup.cli.noPassword'), $this->getStreamFilterBuffer());
        $this->assertSame([], $this->service()->repository()->files());

        putenv('BACKUP_PASSWORD=' . self::PASSWORD);
        $this->resetStreamFilterBuffer();
        command('backup:create --only=db');
        $files = $this->service()->repository()->files();
        $this->assertCount(1, $files);
        $this->assertStringContainsString(lang('Backup.msg.created', [$files[0], ui_bytes((int) filesize($this->backupPath($files[0])))]), $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('backup:list');
        $this->assertStringContainsString($files[0], $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('backup:verify ' . $files[0]);
        $this->assertStringContainsString(lang('Backup.msg.verified'), $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('backup:verify ../../.env');
        $this->assertStringContainsString(lang('Backup.error.notFound'), $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('backup:create --only=everything');
        $this->assertStringContainsString(lang('Backup.error.type'), $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('backup:prune');
        $this->assertStringContainsString(lang('Backup.msg.pruned', [0]), $this->getStreamFilterBuffer());

        $this->createUser('later@example.com');
        $this->resetStreamFilterBuffer();
        command('backup:restore ' . $files[0] . ' --yes');
        $this->assertStringContainsString(lang('Backup.msg.restored'), $this->getStreamFilterBuffer());
        $this->assertSame(0, db_connect()->table('users')->where('username', 'later')->countAllResults());
    }

    private function notificationsOf(int $userId): int
    {
        return db_connect()->table('notifications')->where('user_id', $userId)->countAllResults();
    }
}
