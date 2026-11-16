<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Modules\Updates\Config\Updates as UpdatesConfig;

/**
 * The result of the last update check, kept in `writable/updates/status.json` (0640): the page,
 * the widget and `app:update --dry-run` read it without any outbound request. Not the database
 * nor the cache: it must survive "clear caches" and be readable while an update runs.
 *
 * Shape: `checkedAt` (UTC), `error` (the check failed: message), `core` and `modules[<name>]`
 * (`installed`, `repository`, `latest` (Release::toArray()) or null, `newer`, `error`) and
 * `notified` (`<name>@<version>` already notified).
 */
final class UpdateStatus
{
    private readonly string $file;

    public function __construct(?UpdatesConfig $config = null)
    {
        $config ??= config(UpdatesConfig::class);
        $this->file = rtrim($config->path, '/') . '/status.json';
    }

    public function file(): string
    {
        return $this->file;
    }

    /**
     * @return array<string, mixed> empty before the first check
     */
    public function read(): array
    {
        if (! is_file($this->file)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($this->file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $status
     */
    public function write(array $status): void
    {
        $dir = dirname($this->file);

        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($tmp, 0640);
        rename($tmp, $this->file);
    }

    /**
     * The latest release of the application known from the last check, when it is newer.
     */
    public function newerCore(): ?Release
    {
        $core = $this->read()['core'] ?? null;

        if (! is_array($core) || ($core['newer'] ?? false) !== true || ! is_array($core['latest'] ?? null)) {
            return null;
        }

        return Release::fromArray($core['latest']);
    }
}
