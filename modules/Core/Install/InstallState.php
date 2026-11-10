<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * Whether the application has been configured: a `.env` file in the project root or the lock
 * file `writable/install.lock` (written by the installer, ignored by git). While neither exists,
 * the web installer answers at `install` and every other web request is sent there; as soon as
 * one exists, the web installer answers 404.
 */
final class InstallState
{
    public const LOCK_FILE = 'install.lock';

    public function __construct(
        private readonly string $rootPath = ROOTPATH,
        private readonly string $writablePath = WRITEPATH,
        private readonly bool $assumeConfigured = false,
    ) {
    }

    public function envPath(): string
    {
        return rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . '.env';
    }

    public function lockPath(): string
    {
        return rtrim($this->writablePath, '/\\') . DIRECTORY_SEPARATOR . self::LOCK_FILE;
    }

    public function hasEnv(): bool
    {
        return is_file($this->envPath());
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    /**
     * Configured by the web or by the console: `.env` or the lock file exists.
     */
    public function isConfigured(): bool
    {
        return $this->assumeConfigured || $this->hasEnv() || $this->isLocked();
    }

    /**
     * Whether the installer may write `.env` into the project root.
     */
    public function rootWritable(): bool
    {
        $env = $this->envPath();

        return is_file($env) ? is_writable($env) : is_writable($this->rootPath);
    }

    /**
     * The lock file's data (installed_at, version), or null.
     *
     * @return array<string, mixed>|null
     */
    public function lockData(): ?array
    {
        if (! $this->isLocked()) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->lockPath()), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeLock(array $data): void
    {
        $path = $this->lockPath();

        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) {
            throw new InstallException(lang('Install.error.lock', [$path]));
        }

        @chmod($path, 0640);
    }
}
