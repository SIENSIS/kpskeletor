<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Closure;
use CodeIgniter\Events\Events;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * What surrounds every install, upgrade and uninstall (docs/packages.md): a backup through the
 * `packageBackup` extension point (the Backup module) and maintenance mode while the code
 * changes. Maintenance mode is left as it was found: an operator who had turned it on keeps it.
 */
final class Safeguards
{
    public const MAINTENANCE_REASON = 'packages';

    private bool $wasOn = false;

    /**
     * @param (Closure(PackageBackup): void)|null $backup Makes the backup (default: the `packageBackup` event)
     */
    public function __construct(
        private readonly MaintenanceMode $maintenance = new MaintenanceMode(),
        private readonly ?Closure $backup = null,
    ) {
    }

    /**
     * @return string|null The backup file, or null when the operator chose to go on without one
     *
     * @throws PackageRefused when no backup could be made and $required
     */
    public function backup(string $reason, ?string $password, bool $required, ?int $userId = null): ?string
    {
        $request = new PackageBackup($reason, $password, $userId);

        if ($this->backup !== null) {
            ($this->backup)($request);
        } else {
            Events::trigger(PackageBackup::EVENT, $request);
        }

        if ($request->file() !== null) {
            return $request->file();
        }

        if ($required) {
            throw new PackageRefused($request->error() !== null
                ? lang('Core.packages.backupFailed', [$request->error()])
                : lang('Core.packages.noBackup'));
        }

        return null;
    }

    public function enterMaintenance(): void
    {
        $this->wasOn = $this->maintenance->isOn();

        if (! $this->wasOn) {
            $this->maintenance->enable(self::MAINTENANCE_REASON);
        }
    }

    public function leaveMaintenance(): void
    {
        if (! $this->wasOn) {
            $this->maintenance->disable();
        }
    }
}
