<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Backup\Config\Backup as BackupConfig;

/**
 * The backup files of `writable/backups` and their index (table `backups`).
 *
 * File names are generated, never chosen by a user: `backup-<Ymd>-<His>-<kind>-<8 hex>.kpbackup`
 * (kind: `full`, `db`, `files`, `safety` or `upload`); every path a request names is checked
 * against that pattern (no traversal, nothing outside the folder).
 */
final class BackupRepository
{
    public const NAME = '/\Abackup-\d{8}-\d{6}-(full|db|files|safety|upload)-[a-f0-9]{8}\.kpbackup\z/';

    public const TYPES = ['full', 'db', 'files'];

    public const SOURCES = ['console', 'ui', 'schedule', 'safety', 'update', 'package', 'upload', 'found'];

    private readonly BackupConfig $config;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(?BackupConfig $config = null, private readonly ?BaseConnection $db = null)
    {
        $this->config = $config ?? config(BackupConfig::class);
    }

    public function folder(): string
    {
        $folder = rtrim($this->config->path, '/');

        if (! is_dir($folder)) {
            @mkdir($folder, $this->config->dirPermission, true);
        }

        if (! is_file($folder . '/index.html')) {
            @file_put_contents($folder . '/index.html', '');
        }

        return $folder;
    }

    public static function isValidName(string $file): bool
    {
        return preg_match(self::NAME, $file) === 1;
    }

    /**
     * The absolute path of an existing backup file.
     *
     * @throws BackupException
     */
    public function path(string $file): string
    {
        if (! self::isValidName($file)) {
            throw new BackupException(lang('Backup.error.notFound'));
        }

        $path = $this->folder() . '/' . $file;

        if (! is_file($path) || is_link($path)) {
            throw new BackupException(lang('Backup.error.notFound'));
        }

        return $path;
    }

    /**
     * A new, unused file name.
     */
    public function newName(string $kind, ?Time $at = null): string
    {
        $at ??= Time::now('UTC');

        do {
            $name = sprintf('backup-%s-%s-%s.kpbackup', $at->format('Ymd-His'), $kind, bin2hex(random_bytes(4)));
        } while (is_file($this->folder() . '/' . $name));

        return $name;
    }

    /**
     * The backup files in the folder, newest first.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = [];

        foreach (scandir($this->folder()) ?: [] as $entry) {
            if (self::isValidName($entry) && is_file($this->folder() . '/' . $entry) && ! is_link($this->folder() . '/' . $entry)) {
                $files[] = $entry;
            }
        }

        rsort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function record(array $row): int
    {
        $row += ['created_at' => Time::now('UTC')->format('Y-m-d H:i:s')];
        $this->table()->insert($row);

        return (int) $this->db()->insertID();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->table()->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByFile(string $file): ?array
    {
        $row = $this->table()->where('file', $file)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function markVerified(string $file, bool $ok): void
    {
        $this->table()->where('file', $file)->update([
            'verified_at'   => Time::now('UTC')->format('Y-m-d H:i:s'),
            'verify_result' => $ok ? 'success' : 'failed',
        ]);
    }

    /**
     * Deletes a backup file and its row.
     */
    public function delete(string $file): void
    {
        if (self::isValidName($file)) {
            $path = $this->folder() . '/' . $file;

            if (is_file($path) && ! is_link($path)) {
                @unlink($path);
            }
        }

        $this->table()->where('file', $file)->delete();
    }

    /**
     * Brings the index in line with the folder: rows for files without one (source `found`, the
     * time from the file name), no rows for successful backups whose file is gone.
     */
    public function reconcile(): void
    {
        if (! $this->db()->tableExists('backups')) {
            return;
        }

        $files = $this->files();
        $known = array_column($this->table()->select('file')->where('file IS NOT NULL')->get()->getResultArray(), 'file');

        foreach (array_diff($files, $known) as $file) {
            preg_match(self::NAME, $file, $match);
            $kind = $match[1] ?? 'full';
            $at   = Time::createFromFormat('Ymd-His', substr($file, 7, 15), 'UTC');
            $this->record([
                'file'       => $file,
                'size'       => (int) filesize($this->folder() . '/' . $file),
                'type'       => in_array($kind, self::TYPES, true) ? $kind : 'full',
                'source'     => $kind === 'safety' ? 'safety' : ($kind === 'upload' ? 'upload' : 'found'),
                'result'     => 'success',
                'created_at' => $at->format('Y-m-d H:i:s'),
            ]);
        }

        $gone = array_diff($known, $files);

        if ($gone !== []) {
            $this->table()->whereIn('file', array_values($gone))->where('result', 'success')->delete();
        }
    }

    /**
     * The newest backup (any result), or null.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $row = $this->table()->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Successful backups with a file, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function successful(): array
    {
        return $this->table()->where('result', 'success')->where('file IS NOT NULL')
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /**
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function table(): BaseBuilder
    {
        return $this->db()->table('backups');
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
