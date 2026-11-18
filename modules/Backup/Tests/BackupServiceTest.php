<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupService;
use Modules\Backup\Services\EnvFile;
use Modules\Backup\Services\RestoreService;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * Create, verify and restore on SQLite (criterion 8): contents and checksums, 0640 files, the
 * 12-character password, and everything a restore must refuse before touching anything (wrong
 * password, modified file, newer backup, zip-slip, unknown migrations); a restore brings the
 * database and the files back, makes a safety backup, leaves maintenance mode and never writes
 * the `.env` (it reports the secrets that differ and the merged content).
 *
 * @internal
 */
final class BackupServiceTest extends BackupTestCase
{
    public function testAFullBackupHasTheDatabaseTheFilesAndTheEnv(): void
    {
        file_put_contents($this->root . '/public/photo.jpg', 'jpeg');
        mkdir($this->root . '/private/users/1', 0750, true);
        file_put_contents($this->root . '/private/users/1/doc.pdf', 'pdf');

        $row = $this->service()->create(self::PASSWORD, 'full', 'console');

        $this->assertMatchesRegularExpression('/^backup-\d{8}-\d{6}-full-[a-f0-9]{8}\.kpbackup$/', $row['file']);
        $this->assertSame(0640, fileperms($this->backupPath($row['file'])) & 0777);
        $this->assertSame(1, db_connect()->table('backups')->where('file', $row['file'])->where('result', 'success')->countAllResults());
        $this->assertCount(1, $this->entries('create'));

        $opened   = $this->service()->open($row['file'], self::PASSWORD);
        $manifest = $opened->manifest;
        $entries  = $opened->zip->entries();
        $env      = $opened->env();
        $opened->close();

        $this->assertSame('full', $manifest['type']);
        $this->assertArrayHasKey('Backup', $manifest['modules']);
        $this->assertArrayHasKey('users', $manifest['tables']);
        $this->assertArrayHasKey('database/users.jsonl', $entries);
        $this->assertArrayHasKey('files/public/photo.jpg', $entries);
        $this->assertArrayHasKey('files/private/users/1/doc.pdf', $entries);
        $this->assertArrayNotHasKey('files/public/index.html', $entries, 'The folder\'s own index.html belongs to the installation.');
        $this->assertSame(['public', 'private'], $manifest['contents']['files'], 'writable/uploads only when not empty.');
        $this->assertStringContainsString('app.maskKey', (string) $env);
        $this->assertSame(hash('sha256', 'jpeg'), $manifest['checksums']['files/public/photo.jpg']);
        $this->assertFalse($manifest['demo']);

        file_put_contents($this->root . '/uploads/kpapy.txt', 'x');
        $opened = $this->service()->open($this->service()->create(self::PASSWORD, 'files')['file'], self::PASSWORD);
        $this->assertContains(BackupService::UPLOADS_AREA, $opened->fileAreas());
        $this->assertFalse($opened->hasDatabase());
        $opened->close();
    }

    public function testThePasswordIsMandatoryAndAtLeastTwelveCharacters(): void
    {
        foreach (['', 'short', 'elevenchars', '            '] as $password) {
            try {
                $this->service()->create($password);
                $this->fail("Accepted: '{$password}'");
            } catch (BackupException $e) {
                $this->assertSame(lang('Backup.error.passwordShort', [12]), $e->getMessage());
            }
        }

        $this->assertSame([], $this->service()->repository()->files());
    }

    public function testVerifyAcceptsTheRightPasswordAndRefusesAWrongOneOrAModifiedFile(): void
    {
        $file = $this->service()->create(self::PASSWORD, 'db')['file'];

        $this->assertSame('db', $this->service()->verify($file, self::PASSWORD)['type']);
        $this->assertSame('success', $this->service()->repository()->findByFile($file)['verify_result'] ?? null);

        try {
            $this->service()->verify($file, 'wrong-password-123');
            $this->fail('Wrong password accepted.');
        } catch (BackupException $e) {
            $this->assertSame(lang('Backup.error.password'), $e->getMessage());
        }

        $this->assertCount(1, $this->securityEntries('verify_failed'));

        $data = (string) file_get_contents($this->backupPath($file));
        $data[intdiv(strlen($data), 2)] = chr(ord($data[intdiv(strlen($data), 2)]) ^ 0x20);
        file_put_contents($this->backupPath($file), $data);

        $this->expectException(BackupException::class);
        $this->service()->verify($file, self::PASSWORD);
    }

    public function testARestoreBringsBackTheDatabaseAndTheFiles(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        file_put_contents($this->root . '/public/photo.jpg', 'jpeg');
        $file = $this->service()->create(self::PASSWORD)['file'];

        // Changes after the backup.
        $this->createUser('later@example.com');
        db_connect()->table('users')->where('id', $admin->id)->update(['username' => 'changed']);
        unlink($this->root . '/public/photo.jpg');
        file_put_contents($this->root . '/public/new.jpg', 'new');
        $users = db_connect()->table('users')->countAllResults();

        $steps  = [];
        $result = (new RestoreService($this->config))->restore($file, self::PASSWORD, null, null, 'console', static function (string $step, int $percent) use (&$steps): void {
            $steps[] = $step;
        });

        $db = db_connect();
        $this->assertSame($users - 1, $db->table('users')->countAllResults());
        $this->assertSame(0, $db->table('users')->where('username', 'later')->countAllResults());
        $this->assertSame('admin', $db->table('users')->where('id', $admin->id)->get()->getRow()->username);
        $this->assertSame('jpeg', file_get_contents($this->root . '/public/photo.jpg'));
        $this->assertFileDoesNotExist($this->root . '/public/new.jpg');
        $this->assertFileExists($this->root . '/public/index.html', 'The folder\'s own files stay.');

        $this->assertSame(['database', 'files'], $result->parts);
        $this->assertNotNull($result->safetyFile);
        $this->assertFileExists($this->backupPath((string) $result->safetyFile));
        $this->assertSame(1, $db->table('backups')->where('file', $result->safetyFile)->where('source', 'safety')->countAllResults(), 'The index knows the safety backup after the database was replaced.');
        $this->assertSame(['verify', 'prepare', 'safety', 'database', 'files', 'sync', 'done'], array_values(array_unique($steps)));
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertCount(1, $this->entries('restore'));
        $this->assertSame([], $result->envDifferences);
    }

    public function testOnlyTheFilesOrOnlyTheDatabase(): void
    {
        file_put_contents($this->root . '/public/photo.jpg', 'jpeg');
        $file = $this->service()->create(self::PASSWORD)['file'];
        $this->createUser('later@example.com');
        unlink($this->root . '/public/photo.jpg');

        (new RestoreService($this->config))->restore($file, self::PASSWORD, 'files');
        $this->assertFileExists($this->root . '/public/photo.jpg');
        $this->assertSame(1, db_connect()->table('users')->where('username', 'later')->countAllResults(), 'The database was not touched.');

        unlink($this->root . '/public/photo.jpg');
        (new RestoreService($this->config))->restore($file, self::PASSWORD, 'db');
        $this->assertFileDoesNotExist($this->root . '/public/photo.jpg', 'The files were not touched.');
        $this->assertSame(0, db_connect()->table('users')->where('username', 'later')->countAllResults());
    }

    public function testTheEnvIsNeverWrittenButItsSecretsAreReported(): void
    {
        $file   = $this->service()->create(self::PASSWORD, 'db')['file'];
        $before = "CI_ENVIRONMENT = production\napp.baseURL = 'https://new.example.com/'\nencryption.key = hex2bin:" . str_repeat('cc', 32) . "\n";
        file_put_contents($this->root . '/.env', $before);

        $result = (new RestoreService($this->config))->restore($file, self::PASSWORD);

        $this->assertSame($before, file_get_contents($this->root . '/.env'), 'The restore never writes the .env.');
        $this->assertSame(['encryption.key', 'app.maskKey'], $result->envDifferences);
        $this->assertStringContainsString("app.baseURL = 'https://new.example.com/'", (string) $result->mergedEnv);
        $this->assertStringContainsString('encryption.key = hex2bin:' . str_repeat('aa', 32), (string) $result->mergedEnv);
        $this->assertStringNotContainsString(str_repeat('cc', 32), (string) $result->mergedEnv);
        $this->assertStringContainsString('app.maskKey = ' . str_repeat('b', 64), (string) $result->mergedEnv);
        $this->assertStringContainsString('CI_ENVIRONMENT = production', (string) $result->mergedEnv);

        $env = new EnvFile($this->root . '/.env');
        $env->write((string) $result->mergedEnv);
        $this->assertSame([], $env->differences((string) $env->current(), (string) $result->backupEnv));
    }

    public function testRefusalsHappenBeforeAnythingIsTouched(): void
    {
        $this->createUser('ana2@example.com');
        file_put_contents($this->root . '/public/keep.jpg', 'keep');
        $users = db_connect()->table('users')->countAllResults();

        $cases = [
            'newer Core'     => [$this->craft(['files/public/a.jpg' => 'a'], ['core' => '99.0.0']), lang('Backup.error.newer', ['Core', '99.0.0', \Modules\Core\Config\Version::CORE])],
            'newer module'   => [$this->craft(['files/public/a.jpg' => 'a'], ['modules' => ['Backup' => '9.0.0']]), lang('Backup.error.newer', ['Backup', '9.0.0', '0.1.0'])],
            'unknown module' => [$this->craft(['files/public/a.jpg' => 'a'], ['modules' => ['Ghost' => '0.1.0']]), lang('Backup.error.missingModule', ['Ghost'])],
            'zip-slip'       => [$this->craft(['files/public/../../../evil.php' => '<?php']), lang('Core.archive.badName', ['files/public/../../../evil.php'])],
            'extra entry'    => [$this->craft(['files/public/a.jpg' => 'a'], ['checksums' => []]), lang('Backup.error.damaged')],
            'checksum'       => [$this->craft(['files/public/a.jpg' => 'a'], ['checksums' => ['files/public/a.jpg' => hash('sha256', 'b')]]), lang('Backup.error.checksum', ['files/public/a.jpg'])],
            'migrations'     => [$this->craft([
                'database/migrations.jsonl' => "{\"columns\":[\"id\",\"version\",\"class\",\"group\",\"namespace\",\"time\",\"batch\"]}\n[1,\"2099-01-01-000000\",\"Modules\\\\Ghost\\\\Database\\\\Migrations\\\\CreateGhosts\",\"default\",\"Modules\\\\Ghost\",1,1]\n",
            ], ['type' => 'db', 'contents' => ['database' => true, 'files' => [], 'env' => false]]), lang('Backup.error.unknownMigrations', ['Modules\Ghost\Database\Migrations\CreateGhosts'])],
        ];

        foreach ($cases as $case => [$file, $message]) {
            try {
                (new RestoreService($this->config))->restore($file, self::PASSWORD);
                $this->fail("Accepted: {$case}");
            } catch (BackupException $e) {
                $this->assertSame($message, $e->getMessage(), $case);
            }
        }

        try {
            (new RestoreService($this->config))->restore($cases['newer Core'][0], 'wrong-password-123');
            $this->fail('Wrong password accepted.');
        } catch (BackupException $e) {
            $this->assertSame(lang('Backup.error.password'), $e->getMessage());
        }

        $this->assertSame($users, db_connect()->table('users')->countAllResults());
        $this->assertSame('keep', file_get_contents($this->root . '/public/keep.jpg'));
        $this->assertFileDoesNotExist($this->root . '/public/a.jpg');
        $this->assertSame([], glob($this->config->path . '/*safety*') ?: [], 'No safety backup: nothing was about to change.');
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertCount(count($cases) + 1, [...$this->securityEntries('restore_refused'), ...$this->securityEntries('restore_failed')]);

        // A modified backup (one byte changed) fails authentication before anything is touched.
        $file = $this->craft(['files/public/a.jpg' => 'a']);
        $data = (string) file_get_contents($this->backupPath($file));
        $data[intdiv(strlen($data), 2)] = chr(ord($data[intdiv(strlen($data), 2)]) ^ 0x20);
        file_put_contents($this->backupPath($file), $data);

        try {
            (new RestoreService($this->config))->restore($file, self::PASSWORD);
            $this->fail('A modified backup was accepted.');
        } catch (BackupException) {
            // refused
        }

        $this->assertSame($users, db_connect()->table('users')->countAllResults());
        $this->assertFileDoesNotExist($this->root . '/public/a.jpg');
        $this->assertSame([], glob($this->config->path . '/*safety*') ?: []);
        $this->assertFalse((new MaintenanceMode())->isOn());
    }

    public function testServerCodeIsNeverRestoredIntoThePublicDisk(): void
    {
        $file = $this->craft(['files/public/ok.jpg' => 'ok', 'files/public/shell.php' => '<?php', 'files/public/sub/.htaccess' => 'Allow from all']);

        (new RestoreService($this->config))->restore($file, self::PASSWORD);

        $this->assertSame('ok', file_get_contents($this->root . '/public/ok.jpg'));
        $this->assertFileDoesNotExist($this->root . '/public/shell.php');
        $this->assertFileDoesNotExist($this->root . '/public/sub/.htaccess');
    }
}
