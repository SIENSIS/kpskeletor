<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Modules\Backup\Config\Backup as BackupConfig;

/**
 * Backup passwords: never stored; at least `Config\Backup::$minPasswordLength` characters
 * (12, D-00-47 and spec "Backup"); scheduled and non-interactive runs read `backup.password` from
 * `.env` or the `BACKUP_PASSWORD` environment variable.
 */
final class BackupPassword
{
    private readonly BackupConfig $config;

    public function __construct(?BackupConfig $config = null)
    {
        $this->config = $config ?? config(BackupConfig::class);
    }

    public function minLength(): int
    {
        return max(12, $this->config->minPasswordLength);
    }

    /**
     * The reason a password is refused for a new backup, or null when it is valid.
     */
    public function problem(string $password): ?string
    {
        if (mb_strlen($password) < $this->minLength()) {
            return lang('Backup.error.passwordShort', [$this->minLength()]);
        }

        if (trim($password) === '') {
            return lang('Backup.error.passwordShort', [$this->minLength()]);
        }

        return null;
    }

    /**
     * @throws BackupException when the password is too weak for a new backup
     */
    public function assertStrong(string $password): void
    {
        $problem = $this->problem($password);

        if ($problem !== null) {
            throw new BackupException($problem);
        }
    }

    /**
     * The configured password (`backup.password` in `.env`, or `BACKUP_PASSWORD`), or null.
     */
    public function configured(): ?string
    {
        foreach (['backup.password', 'BACKUP_PASSWORD'] as $name) {
            $value = $_ENV[$name] ?? service('superglobals')->server($name) ?? getenv($name);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
