<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use Closure;
use JsonException;

/**
 * The web installer's jobs (session 39): files in `writable/install-jobs/` (folder 0700, files
 * 0600), never database rows (there is no database yet), like the Backup module's JobStore.
 *
 * - `<id>.json`: status (`queued|running|done|failed`), the current step, its counts and
 *   parameters, the percentage, the log (language keys and parameters, never secrets), the
 *   language of the page that started it and the non-secret result shown by the result page.
 * - `<id>.secrets.json`: the one-time values of the result page (the `.env` content to copy, the
 *   demo users' passwords), deleted the first time they are read (`takeSecrets()`).
 * - `running.lock`: only one installation at a time (`lock()`); a stale lock older than the TTL
 *   is ignored.
 *
 * The id (32 random hex characters, 128 bits) is the capability to read a job. A job lives
 * `TTL` seconds: afterwards `get()` answers null and `purge()` deletes its files.
 */
final class InstallJobs
{
    public const ID = '/\A[a-f0-9]{32}\z/';

    /** Seconds a job (and its result page) can be read. */
    public const TTL = 3600;

    /** Log lines kept per job (the oldest are dropped). */
    public const MAX_LOG = 100;

    private const LOCK = 'running.lock';

    private readonly InstallState $state;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param (Closure(): int)|null $clock Current Unix time (tests)
     */
    public function __construct(?InstallState $state = null, private readonly int $ttl = self::TTL, ?Closure $clock = null)
    {
        $this->state = $state ?? service('installstate');
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * A new queued job; purges the expired ones first.
     */
    public function create(string $locale): string
    {
        $this->purge();

        $id  = bin2hex(random_bytes(16));
        $now = ($this->clock)();
        $this->write($this->file($id), [
            'id'         => $id,
            'status'     => 'queued',
            'step'       => 'queued',
            'done'       => 0,
            'total'      => 0,
            'params'     => [],
            'percent'    => 0,
            'log'        => [],
            'locale'     => preg_match('/\A[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})?\z/', $locale) === 1 ? $locale : '', // checked against the site's locales when read
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * The job, or null when the id is not valid, unknown or expired.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (preg_match(self::ID, $id) !== 1 || ! is_file($this->file($id))) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->file($id)), true);

        if (! is_array($data) || (int) ($data['created_at'] ?? 0) + $this->ttl < ($this->clock)()) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function update(string $id, array $changes): void
    {
        $job = $this->get($id);

        if ($job !== null) {
            $this->write($this->file($id), array_merge($job, $changes, ['updated_at' => ($this->clock)()]));
        }
    }

    /**
     * Adds a log line (a language key and its parameters) and applies other changes at once.
     *
     * @param array<string, scalar> $params
     * @param array<string, mixed>  $changes
     */
    public function log(string $id, string $key, array $params = [], array $changes = []): void
    {
        $job = $this->get($id);

        if ($job === null) {
            return;
        }

        $lines   = is_array($job['log'] ?? null) ? $job['log'] : [];
        $lines[] = ['key' => $key, 'params' => $params];

        $this->write($this->file($id), array_merge($job, $changes, [
            'log'        => array_slice($lines, -self::MAX_LOG),
            'updated_at' => ($this->clock)(),
        ]));
    }

    /**
     * Deletes a job and its one-time values.
     */
    public function delete(string $id): void
    {
        if (preg_match(self::ID, $id) === 1) {
            @unlink($this->file($id));
            @unlink($this->secretsFile($id));
        }
    }

    /**
     * Keeps the one-time values of the result page.
     *
     * @param array<string, mixed> $secrets
     */
    public function putSecrets(string $id, array $secrets): void
    {
        if (preg_match(self::ID, $id) === 1) {
            $this->write($this->secretsFile($id), $secrets);
        }
    }

    /**
     * The one-time values, deleted once read (null when already read, or for none).
     *
     * @return array<string, mixed>|null
     */
    public function takeSecrets(string $id): ?array
    {
        if ($this->get($id) === null || ! is_file($this->secretsFile($id))) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->secretsFile($id)), true);
        @unlink($this->secretsFile($id));

        return is_array($data) ? $data : null;
    }

    /**
     * Deletes the files of expired jobs and a stale lock.
     *
     * @return int files deleted
     */
    public function purge(): int
    {
        $folder = $this->folder();
        $limit  = ($this->clock)() - $this->ttl;
        $count  = 0;

        foreach (glob($folder . '/*.json') ?: [] as $file) {
            $id   = explode('.', basename($file), 2)[0];
            $data = json_decode((string) @file_get_contents($folder . '/' . $id . '.json'), true);
            $born = is_array($data) ? (int) ($data['created_at'] ?? 0) : (int) @filemtime($file);

            if ($born < $limit && @unlink($file)) {
                $count++;
            }
        }

        $lock = $folder . '/' . self::LOCK;

        if (is_file($lock) && (int) @filemtime($lock) < $limit && @unlink($lock)) {
            $count++;
        }

        return $count;
    }

    /**
     * Takes the installation lock for `$owner` (a job id or a request's own id); false while
     * another installation holds it.
     */
    public function lock(string $owner): bool
    {
        $this->purge();

        $handle = @fopen($this->folder() . '/' . self::LOCK, 'x');

        if ($handle === false) {
            return false;
        }

        fwrite($handle, $owner);
        fclose($handle);
        @chmod($this->folder() . '/' . self::LOCK, 0600);

        return true;
    }

    public function isLocked(): bool
    {
        $this->purge();

        return is_file($this->folder() . '/' . self::LOCK);
    }

    /**
     * Releases the lock when `$owner` holds it.
     */
    public function unlock(string $owner): void
    {
        $lock = $this->folder() . '/' . self::LOCK;

        if (is_file($lock) && hash_equals((string) @file_get_contents($lock), $owner)) {
            @unlink($lock);
        }
    }

    /**
     * The job's file (for tests and diagnostics).
     */
    public function file(string $id): string
    {
        return $this->folder() . '/' . $id . '.json';
    }

    public function secretsFile(string $id): string
    {
        return $this->folder() . '/' . $id . '.secrets.json';
    }

    private function folder(): string
    {
        $folder = $this->state->jobsPath();

        if (! is_dir($folder)) {
            @mkdir($folder, 0700, true);
        }

        return $folder;
    }

    /**
     * Atomic write, 0600 before the file gets its name.
     *
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    private function write(string $file, array $data): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $file);
    }
}
