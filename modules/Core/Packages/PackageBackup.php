<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * The backup made before a package is installed, upgraded or uninstalled, as an extension point
 * (the Core never names a module): PackageInstaller and Uninstaller trigger the event
 * `packageBackup` with an instance; the Backup module's listener makes a full backup and calls
 * done() with its file name, or failed() with the reason. Listeners check that their module is
 * enabled. Without any answer the operation stops unless the operator chose to go on without a
 * backup (`--no-backup`).
 */
final class PackageBackup
{
    public const EVENT = 'packageBackup';

    private ?string $file = null;

    private ?string $error = null;

    /**
     * @param string      $reason   What is about to change, e.g. "install News 1.2.0"
     * @param string|null $password The backup password given by the operator (null: the configured one)
     */
    public function __construct(
        public readonly string $reason,
        public readonly ?string $password = null,
        public readonly ?int $userId = null,
    ) {
    }

    public function done(string $file): void
    {
        $this->file ??= $file;
    }

    public function failed(string $error): void
    {
        $this->error ??= $error;
    }

    public function file(): ?string
    {
        return $this->file;
    }

    public function error(): ?string
    {
        return $this->error;
    }
}
