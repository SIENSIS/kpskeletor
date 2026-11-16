<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Storage as StorageConfig;
use FilesystemIterator;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Core\Archive\ArchiveRefused;
use Modules\Core\Archive\SafeZip;
use Modules\Core\Config\Version;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ModuleDiscovery;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Creates, opens and verifies backups (spec "Backup"; docs/backup-restore.md). The console, the
 * back office and the scheduled task use this one service.
 *
 * A backup is a ZIP, encrypted as a whole by ArchiveCrypto, with:
 * - `manifest.json`: format, application and module versions, date, type, source, demo flag,
 *   contents, rows per table and the SHA-256 of every other entry;
 * - `database/<table>.jsonl`: the portable dump (DatabaseDumper), types `full` and `db`;
 * - `files/<area>/…`: every storage disk of `Config\Storage` and `writable/uploads` (area
 *   `writable-uploads`, only when it is not empty), types `full` and `files`. The paths modules
 *   declare in `$backup['files']` live in those disks and are listed in the manifest;
 * - `env/.env`: the `.env` with its secrets (the reason a password is mandatory), every type.
 */
final class BackupService
{
    public const FORMAT = 1;

    public const MANIFEST = 'manifest.json';

    public const ENV_ENTRY = 'env/.env';

    public const UPLOADS_AREA = 'writable-uploads';

    public const PREFIXES = [self::MANIFEST, 'database/', 'files/', self::ENV_ENTRY];

    private readonly BackupRepository $repository;

    private readonly BackupConfig $config;

    private readonly ArchiveCrypto $crypto;

    private readonly EnvFile $env;

    public function __construct(
        ?BackupConfig $config = null,
        ?BackupRepository $repository = null,
        ?ArchiveCrypto $crypto = null,
        private readonly ?StorageConfig $storage = null,
        ?EnvFile $env = null,
    ) {
        $this->config     = $config ?? config(BackupConfig::class);
        $this->repository = $repository ?? new BackupRepository($this->config);
        $this->crypto     = $crypto ?? new ArchiveCrypto($this->config);
        $this->env        = $env ?? new EnvFile($this->config->envPath);
    }

    public function repository(): BackupRepository
    {
        return $this->repository;
    }

    /**
     * Creates a backup; returns its row in the index. A failure is recorded (result `failed`),
     * logged and thrown.
     *
     * @param string $type   `full`, `db` or `files`
     * @param string $source `console`, `ui`, `schedule`, `safety` or `update` (app:update)
     *
     * @return array<string, mixed>
     *
     * @throws BackupException
     */
    public function create(string $password, string $type = 'full', string $source = 'console', ?int $userId = null): array
    {
        (new BackupPassword($this->config))->assertStrong($password);

        if (! in_array($type, BackupRepository::TYPES, true)) {
            throw new BackupException(lang('Backup.error.type'));
        }

        $name   = $this->repository->newName($source === 'safety' ? 'safety' : $type);
        $target = $this->repository->folder() . '/' . $name;
        $work   = $this->workFolder('create');

        try {
            $manifest = $this->build($work, $type, $source, $userId);
            $this->crypto->encryptFile($work . '/archive.zip', $target, $password);
            @chmod($target, $this->config->filePermission);
            $this->copyToDisk($target, $name);

            $row = [
                'file'         => $name,
                'size'         => (int) filesize($target),
                'type'         => $type,
                'source'       => $source,
                'result'       => 'success',
                'demo'         => $manifest['demo'] ? 1 : 0,
                'core_version' => Version::CORE,
                'created_by'   => $userId,
                'created_at'   => $manifest['created_at'],
            ];
            $row['id'] = $this->repository->record($row);

            activity_log('Backup', 'create', ['resource' => 'backup', 'summary' => $name, 'changes' => ['type' => $type, 'source' => $source, 'size' => $row['size']]]);

            return $row;
        } catch (Throwable $e) {
            @unlink($target);
            $message = $e instanceof BackupException || $e instanceof ArchiveRefused ? $e->getMessage() : lang('Backup.error.unexpected');

            try {
                $this->repository->record([
                    'file' => null, 'type' => $type, 'source' => $source, 'result' => 'failed',
                    'message' => mb_substr($message, 0, 500), 'core_version' => Version::CORE, 'created_by' => $userId,
                ]);
                activity_log('Backup', 'create_failed', ['resource' => 'backup', 'summary' => $message, 'changes' => ['type' => $type, 'source' => $source]]);
            } catch (Throwable) {
                // The database itself may be the problem; the exception says what failed.
            }

            if (! $e instanceof BackupException) {
                log_message('error', 'Backup failed: {message}', ['message' => $e->getMessage()]);
            }

            throw $e instanceof BackupException ? $e : new BackupException($message, 0, $e);
        } finally {
            self::removeFolder($work);
        }
    }

    /**
     * Decrypts, authenticates and checks a backup (manifest, checksums, no unexpected entry);
     * nothing outside a private temporary folder is touched. The caller must close() it.
     *
     * @throws BackupException wrong password, modified or damaged file
     */
    public function open(string $file, string $password): OpenedBackup
    {
        $path = $this->repository->path($file);
        $work = $this->workFolder('open');

        try {
            $this->crypto->decryptFile($path, $work . '/archive.zip', $password);
            $zip = SafeZip::open($work . '/archive.zip', self::PREFIXES, $this->config->maxUncompressedBytes, SafeZip::MAX_ENTRIES, $this->config->maxRatio);
        } catch (ArchiveRefused $e) {
            self::removeFolder($work);

            throw new BackupException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            self::removeFolder($work);

            throw $e instanceof BackupException ? $e : new BackupException(lang('Backup.error.damaged'), 0, $e);
        }

        try {
            $manifest = json_decode($zip->read(self::MANIFEST, 64 * 1_048_576), true);

            if (! is_array($manifest) || (int) ($manifest['format'] ?? 0) !== self::FORMAT || ! is_array($manifest['checksums'] ?? null)) {
                throw new BackupException(lang('Backup.error.format'));
            }

            $checksums = $manifest['checksums'];
            $entries   = $zip->entries();
            unset($entries[self::MANIFEST]);

            if (array_diff_key($entries, $checksums) !== [] || array_diff_key($checksums, $entries) !== []) {
                throw new BackupException(lang('Backup.error.damaged'));
            }

            foreach (array_keys($entries) as $name) {
                if (! is_string($checksums[$name]) || ! hash_equals($checksums[$name], $zip->hash($name))) {
                    throw new BackupException(lang('Backup.error.checksum', [$name]));
                }
            }

            return new OpenedBackup($file, $manifest, $zip, $work);
        } catch (Throwable $e) {
            $zip->close();
            self::removeFolder($work);

            if ($e instanceof ArchiveRefused) {
                throw new BackupException($e->getMessage(), 0, $e);
            }

            throw $e instanceof BackupException ? $e : new BackupException(lang('Backup.error.damaged'), 0, $e);
        }
    }

    /**
     * Verifies a backup with its password; returns its manifest. The result is stored and logged.
     *
     * @return array<string, mixed>
     *
     * @throws BackupException
     */
    public function verify(string $file, string $password): array
    {
        try {
            $opened = $this->open($file, $password);
        } catch (BackupException $e) {
            $this->repository->markVerified($file, false);
            security_log('Backup', 'verify_failed', ['resource' => 'backup', 'summary' => $file . ': ' . $e->getMessage()]);

            throw $e;
        }

        $manifest = $opened->manifest;
        $opened->close();
        $this->repository->markVerified($file, true);
        activity_log('Backup', 'verify', ['resource' => 'backup', 'summary' => $file]);

        return $manifest;
    }

    /**
     * The file areas: every storage disk and `writable/uploads`.
     *
     * @return array<string, array{root: string, filePermission: int, dirPermission: int, public: bool}>
     */
    public function areas(): array
    {
        $areas = [];

        foreach (($this->storage ?? config(StorageConfig::class))->disks as $name => $disk) {
            if (preg_match('/^[a-z0-9_-]+$/', (string) $name) !== 1) {
                continue;
            }

            $areas[(string) $name] = [
                'root'           => rtrim((string) $disk['root'], '/'),
                'filePermission' => (int) $disk['filePermission'],
                'dirPermission'  => (int) $disk['dirPermission'],
                'public'         => ($disk['url'] ?? null) !== null,
            ];
        }

        $areas[self::UPLOADS_AREA] = [
            'root'           => rtrim($this->config->uploadsPath, '/'),
            'filePermission' => 0640,
            'dirPermission'  => 0750,
            'public'         => false,
        ];

        return $areas;
    }

    /**
     * Files of a folder (relative paths, no links, no `index.html`/`.htaccess` at its top level:
     * those protect the folder and belong to the installation).
     *
     * @return list<string>
     */
    public static function filesOf(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);

            if (in_array($relative, self::PROTECTED_FILES, true)) {
                continue;
            }

            $files[] = str_replace('\\', '/', $relative);
        }

        sort($files);

        return $files;
    }

    /** Files at the top of an area that belong to the installation (never backed up nor replaced). */
    public const PROTECTED_FILES = ['index.html', '.htaccess', 'web.config'];

    public static function removeFolder(string $folder): void
    {
        if (! is_dir($folder) || is_link($folder)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($folder);
    }

    /**
     * A private (0700) temporary folder inside the backups folder.
     */
    public function workFolder(string $purpose): string
    {
        $folder = $this->repository->folder() . '/tmp/' . $purpose . '-' . bin2hex(random_bytes(8));

        if (! @mkdir($folder, 0700, true) && ! is_dir($folder)) {
            throw new BackupException(lang('Backup.error.write'));
        }

        return $folder;
    }

    /**
     * Writes the plain archive (`archive.zip`) into `$work`; returns the manifest.
     *
     * @return array<string, mixed>
     */
    private function build(string $work, string $type, string $source, ?int $userId): array
    {
        $zip = new ZipArchive();

        if ($zip->open($work . '/archive.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new BackupException(lang('Backup.error.write'));
        }

        $checksums = [];
        $tables    = [];
        $areas     = [];
        $modules   = [];
        $paths     = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $modules[$name] = $module->manifest->version;

                if (($module->manifest->backup['files'] ?? []) !== []) {
                    $paths[$name] = $module->manifest->backup['files'];
                }
            }
        }

        ksort($modules);

        if ($type !== 'files') {
            @mkdir($work . '/database', 0700);
            $tables = (new DatabaseDumper(null, $this->config))->dump($work . '/database');

            foreach (array_keys($tables) as $table) {
                $file = $work . '/database/' . $table . '.jsonl';
                $checksums['database/' . $table . '.jsonl'] = (string) hash_file('sha256', $file);
                $zip->addFile($file, 'database/' . $table . '.jsonl');
            }
        }

        if ($type !== 'db') {
            foreach ($this->areas() as $area => $definition) {
                $files = self::filesOf($definition['root']);

                if ($files === [] && $area === self::UPLOADS_AREA) {
                    continue; // writable/uploads only when not empty (D-00-39)
                }

                $areas[] = $area;

                foreach ($files as $relative) {
                    $entry = 'files/' . $area . '/' . $relative;

                    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $relative) === 1 || str_contains('/' . $relative . '/', '/../')) {
                        continue;
                    }

                    $checksums[$entry] = (string) hash_file('sha256', $definition['root'] . '/' . $relative);
                    $zip->addFile($definition['root'] . '/' . $relative, $entry);
                }
            }
        }

        $env = $this->env->current();

        if ($env !== null) {
            $checksums[self::ENV_ENTRY] = hash('sha256', $env);
            $zip->addFromString(self::ENV_ENTRY, $env);
        }

        $db       = Database::connect();
        $manifest = [
            'format'     => self::FORMAT,
            'core'       => Version::CORE,
            'modules'    => $modules,
            'created_at' => Time::now('UTC')->format('Y-m-d H:i:s'),
            'type'       => $type,
            'source'     => $source,
            'created_by' => $userId,
            'demo'       => $type !== 'files' && $this->hasDemoData(),
            'driver'     => $db->DBDriver,
            'contents'   => ['database' => $type !== 'files', 'files' => $areas, 'env' => $env !== null],
            'tables'     => $tables,
            'modulePaths' => $paths,
            'checksums'  => $checksums,
        ];

        $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! $zip->close()) {
            throw new BackupException(lang('Backup.error.write'));
        }

        return $manifest;
    }

    private function hasDemoData(): bool
    {
        try {
            return (new DemoDataManager())->loadedModules() !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The optional copy to another Storage disk (`Config\Backup::$copyDisk`, never a public one).
     */
    private function copyToDisk(string $path, string $name): void
    {
        $disk = $this->config->copyDisk;

        if ($disk === null || $disk === '') {
            return;
        }

        $target = service('storage')->disk($disk);

        if ($target->isPublic()) {
            throw new BackupException(lang('Backup.error.publicDisk'));
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new BackupException(lang('Backup.error.write'));
        }

        try {
            $target->writeStream('backups/' . $name, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
