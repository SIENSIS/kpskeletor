<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use CodeIgniter\I18n\Time;
use Modules\Backup\Config\Backup as BackupConfig;

/**
 * Background jobs of the back office (restores) with their progress (D-27-04). Files in
 * `writable/backups/jobs/<id>.json` (0600), not database rows: a restore replaces the database
 * while it runs. The id is 32 random hex characters, the capability to read the progress
 * (`progress/backup/<id>`, which shows only the step, the percentage and the status). The merged
 * `.env` of a finished restore is kept apart (`<id>.env`, 0600) until its user reads it once.
 */
final class JobStore
{
    public const ID = '/\A[a-f0-9]{32}\z/';

    private readonly BackupConfig $config;

    public function __construct(?BackupConfig $config = null)
    {
        $this->config = $config ?? config(BackupConfig::class);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(string $kind, int $userId, array $data = []): string
    {
        $id = bin2hex(random_bytes(16));
        $this->write($id, $data + [
            'id' => $id, 'kind' => $kind, 'user' => $userId, 'status' => 'queued', 'step' => 'queued',
            'percent' => 0, 'message' => null, 'created_at' => Time::now('UTC')->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (preg_match(self::ID, $id) !== 1 || ! is_file($this->file($id))) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->file($id)), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function update(string $id, array $changes): void
    {
        $job = $this->get($id);

        if ($job !== null) {
            $this->write($id, array_merge($job, $changes, ['updated_at' => Time::now('UTC')->format('Y-m-d H:i:s')]));
        }
    }

    public function putEnv(string $id, string $content): void
    {
        $path = $this->folder() . '/' . $id . '.env';
        file_put_contents($path, $content, LOCK_EX);
        @chmod($path, 0600);
    }

    /**
     * The merged `.env` of a finished restore, deleted once read.
     */
    public function takeEnv(string $id): ?string
    {
        if (preg_match(self::ID, $id) !== 1) {
            return null;
        }

        $path = $this->folder() . '/' . $id . '.env';

        if (! is_file($path)) {
            return null;
        }

        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /**
     * Deletes finished jobs older than `Config\Backup::$jobHours`.
     */
    public function purge(?Time $now = null): int
    {
        $limit   = ($now ?? Time::now('UTC'))->subHours($this->config->jobHours)->getTimestamp();
        $deleted = 0;

        foreach ([...(glob($this->folder() . '/*.json') ?: []), ...(glob($this->folder() . '/*.env') ?: [])] as $file) {
            if ((int) filemtime($file) < $limit) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function folder(): string
    {
        $folder = rtrim($this->config->path, '/') . '/jobs';

        if (! is_dir($folder)) {
            @mkdir($folder, 0700, true);
        }

        return $folder;
    }

    private function file(string $id): string
    {
        return $this->folder() . '/' . $id . '.json';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $id, array $data): void
    {
        $tmp = $this->file($id) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $this->file($id));
    }
}
