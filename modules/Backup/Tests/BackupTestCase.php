<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use CodeIgniter\Config\Factories;
use Config\Storage as StorageConfig;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\ArchiveCrypto;
use Modules\Backup\Services\BackupService;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Dashboard\Tests\DashboardTestCase;
use ZipArchive;

/**
 * Base of the Backup feature tests: the Auth fixture with every declaration, and a private
 * temporary root for everything a backup reads or writes: `backups/`, both storage disks,
 * `uploads/` and `.env` (Config\Backup and Config\Storage injected). Restores run inside the
 * request (`jobsAfterResponse` false). Fast Argon2id parameters.
 */
abstract class BackupTestCase extends DashboardTestCase
{
    protected const PASSWORD = 'correct-horse-battery';

    protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'Backup'];

    protected string $root = '';

    protected BackupConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();

        $this->root = sys_get_temp_dir() . '/kpbackup-test-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/public', 0755, true);
        mkdir($this->root . '/private', 0750, true);
        mkdir($this->root . '/uploads', 0750, true);
        file_put_contents($this->root . '/public/index.html', '');
        file_put_contents($this->root . '/.env', "CI_ENVIRONMENT = testing\nencryption.key = hex2bin:" . str_repeat('aa', 32) . "\napp.maskKey = " . str_repeat('b', 64) . "\n");

        $config                    = new BackupConfig();
        $config->path              = $this->root . '/backups';
        $config->uploadsPath       = $this->root . '/uploads';
        $config->envPath           = $this->root . '/.env';
        $config->jobsAfterResponse = false;
        $config->opsLimit          = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
        $config->memLimit          = 8 * 1024 * 1024;
        $this->config              = $config;
        Factories::injectMock('config', BackupConfig::class, $config);

        $storage                           = new StorageConfig();
        $storage->disks['public']['root']  = $this->root . '/public';
        $storage->disks['private']['root'] = $this->root . '/private';
        Factories::injectMock('config', StorageConfig::class, $storage);
        Factories::injectMock('config', 'Storage', $storage);
    }

    protected function tearDown(): void
    {
        (new MaintenanceMode())->disable();
        BackupService::removeFolder($this->root);
        Factories::reset('config');
        parent::tearDown();
    }

    protected function service(): BackupService
    {
        return new BackupService($this->config);
    }

    /**
     * A backup made by hand (to test what the service refuses): the entries are encrypted
     * with the module's own format; the manifest's checksums are computed unless given.
     *
     * @param array<string, string> $entries  name => contents (besides manifest.json)
     * @param array<string, mixed>  $manifest overrides
     */
    protected function craft(array $entries, array $manifest = [], string $password = self::PASSWORD): string
    {
        $zipPath = $this->root . '/crafted-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $checksums = [];

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
            $checksums[$name] = hash('sha256', $contents);
        }

        $zip->addFromString('manifest.json', (string) json_encode($manifest + [
            'format' => 1, 'core' => \Modules\Core\Config\Version::CORE, 'modules' => [], 'created_at' => '2026-11-01 10:00:00',
            'type' => 'files', 'contents' => ['database' => false, 'files' => ['public'], 'env' => false], 'tables' => [], 'checksums' => $checksums,
        ]));
        $zip->close();

        $repository = $this->service()->repository();
        $name       = $repository->newName('upload');
        (new ArchiveCrypto($this->config))->encryptFile($zipPath, $repository->folder() . '/' . $name, $password);
        unlink($zipPath);
        $repository->reconcile();

        return $name;
    }

    protected function backupPath(string $file): string
    {
        return $this->config->path . '/' . $file;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function securityEntries(string $action): array
    {
        return db_connect()->table('activity_log')->where('action', $action)->where('type', 'security')->get()->getResultArray();
    }
}
