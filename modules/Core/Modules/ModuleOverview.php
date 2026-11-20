<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Events\Events;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Packages\ModuleVersions;
use Modules\Core\Packages\StagedPackages;

/**
 * The rows of the Modules screen: every module found on disk or in the registry with its
 * versions (installed, and the latest one when the Updates module knows it), status, dependencies
 * and dependants, and whether it is a core module or has demo data.
 */
final class ModuleOverview
{
    public const STATUS_ENABLED = 'enabled';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_NEW = 'new';

    public const STATUS_PENDING_INSTALL = 'pendingInstall';

    public const STATUS_PENDING_UNINSTALL = 'pendingUninstall';

    public const STATUS_ERROR = 'error';

    public function __construct(
        private readonly ModuleDiscovery $discovery = new ModuleDiscovery(),
        private readonly ?StagedPackages $staged = null,
        private readonly ?DemoDataManager $demo = null,
    ) {
    }

    /**
     * @return array<string, array{name: string, icon: string, description: string, author: string, version: string, installed: string|null, latest: string|null, status: string, errors: list<string>, requires: array<string, string>, dependants: list<string>, core: bool, developmentOnly: bool, blocked: bool, demo: bool, demoLoaded: bool, enabled: bool}>
     */
    public function rows(): array
    {
        $modules  = $this->discovery->discover();
        $registry = model(ModuleModel::class, false)->allByName();
        $waiting  = ($this->staged ?? new StagedPackages())->waiting();
        $demo     = $this->demo ?? new DemoDataManager();
        $loaded   = $demo->loadedModules();
        $versions = new ModuleVersions();
        Events::trigger(ModuleVersions::EVENT, $versions);

        $dependants = [];

        foreach ($modules as $name => $module) {
            foreach (array_keys($module->manifest->requires ?? []) as $required) {
                $dependants[$required][] = $name;
            }
        }

        $rows = [];

        foreach (array_unique([...array_keys($modules), ...array_keys($registry)]) as $name) {
            $module   = $modules[$name] ?? null;
            $manifest = $module?->manifest;
            $row      = $registry[$name] ?? null;
            $errors   = $module !== null ? $module->errors : [lang('Core.modules.folderMissing')];

            $status = match (true) {
                isset($waiting[$name])                          => $waiting[$name] === 'uninstall' ? self::STATUS_PENDING_UNINSTALL : self::STATUS_PENDING_INSTALL,
                $module === null || ! $module->isValid()        => self::STATUS_ERROR,
                $row === null                                   => self::STATUS_NEW,
                $row['enabled']                                 => self::STATUS_ENABLED,
                default                                         => self::STATUS_DISABLED,
            };

            $rows[$name] = [
                'name'        => $name,
                'icon'        => $manifest->icon ?? 'bi bi-box',
                'description' => $manifest !== null && $manifest->description !== '' ? lang($manifest->description) : '',
                'author'      => $manifest->author ?? '',
                'version'     => $manifest->version ?? '',
                'installed'   => $row['version'] ?? null,
                'latest'      => $versions->latest($name),
                'status'      => $status,
                'errors'      => $module !== null && $module->isValid() ? [] : $errors,
                'requires'    => $manifest->requires ?? [],
                'dependants'  => $dependants[$name] ?? [],
                'core'        => in_array($name, ModuleToggler::CORE_MODULES, true),
                // Development-only module (D-41-01) and whether it may not be enabled here.
                'developmentOnly' => $manifest !== null && $manifest->developmentOnly,
                'blocked'         => $manifest !== null && (new DevelopmentOnly())->refuses($manifest),
                'demo'        => $demo->hasSeeder($name),
                'demoLoaded'  => in_array($name, $loaded, true),
                'enabled'     => ($row['enabled'] ?? false) === true,
            ];
        }

        ksort($rows);

        // Core first, then by name.
        if (isset($rows['Core'])) {
            $rows = ['Core' => $rows['Core']] + $rows;
        }

        return $rows;
    }
}
