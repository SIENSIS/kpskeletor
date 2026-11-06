<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\I18n\Time;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Enables and disables modules (`modules:enable`, `modules:disable`, later the Modules screen).
 *
 * Disabling hides a module at once (routes 404, registries, permissions) without deleting any
 * data or grant; enabling it again restores everything. Core modules cannot be disabled, nor a
 * module that enabled modules depend on; enabling a module whose dependencies are disabled needs
 * them enabled too (`$withDependencies`). Enabling runs the module's pending migrations (if they
 * fail the module stays disabled) and publishes its assets.
 */
final class ModuleToggler
{
    /** Modules that can never be disabled. */
    public const CORE_MODULES = ['Core', 'Auth', 'Api', 'Users', 'Settings'];

    public function __construct(
        private readonly ModuleDiscovery $discovery = new ModuleDiscovery(),
        private readonly ModuleMigrator $migrator = new ModuleMigrator(),
        private readonly CacheInvalidator $caches = new CacheInvalidator(),
        private readonly ?ModuleModel $registry = null,
        private readonly ?AssetPublisher $assets = null,
    ) {
    }

    /**
     * @return bool False when the module was already disabled
     *
     * @throws ModuleToggleException
     */
    public function disable(string $name): bool
    {
        $known = $this->registry()->allByName();
        $this->assertInstalled($name, $known);

        if (in_array($name, self::CORE_MODULES, true)) {
            throw new ModuleToggleException(lang('Core.toggle.coreModule', [$name]));
        }

        if (! $known[$name]['enabled']) {
            return false;
        }

        $dependants = [];

        foreach ($this->manifests() as $other => $manifest) {
            if ($other !== $name && isset($manifest->requires[$name]) && ($known[$other]['enabled'] ?? false)) {
                $dependants[] = $other;
            }
        }

        if ($dependants !== []) {
            throw new ModuleToggleException(lang('Core.toggle.hasDependants', [$name, implode(', ', $dependants)]), $dependants);
        }

        $this->setEnabled($known[$name]['id'], false);
        $this->caches->invalidate();
        (new ShieldMatrix())->write(); // its permissions leave Shield's matrix
        activity_log('Core', 'module_disabled', ['resource' => 'modules', 'summary' => $name]);

        return true;
    }

    /**
     * @return list<string> The modules enabled, dependencies first (empty when already enabled)
     *
     * @throws ModuleToggleException
     * @throws \RuntimeException    when a migration fails (the module stays disabled)
     */
    public function enable(string $name, bool $withDependencies = false): array
    {
        $known = $this->registry()->allByName();
        $this->assertInstalled($name, $known);

        if ($known[$name]['enabled']) {
            return [];
        }

        $manifests = $this->manifests();

        if (! isset($manifests[$name])) {
            throw new ModuleToggleException(lang('Core.toggle.invalid', [$name]));
        }

        $order = [];
        $this->collectDisabledDependencies($name, $manifests, $known, $order);
        $missing = array_values(array_diff($order, [$name]));

        if ($missing !== [] && ! $withDependencies) {
            throw new ModuleToggleException(lang('Core.toggle.dependenciesDisabled', [$name, implode(', ', $missing)]), $missing, true);
        }

        foreach ($order as $module) {
            $this->migrator->migrate('Modules\\' . $module);
            $this->setEnabled($known[$module]['id'], true);
            ($this->assets ?? new AssetPublisher())->publish(AssetPublisher::ownerOf($module));
        }

        $this->caches->invalidate();
        (new ShieldMatrix())->write(); // their grants come back
        activity_log('Core', 'module_enabled', ['resource' => 'modules', 'summary' => implode(', ', $order), 'changes' => ['modules' => $order]]);

        return $order;
    }

    /**
     * Depth-first: the disabled dependencies of a module (transitively), then the module.
     *
     * @param array<string, ModuleManifest>                                                                                                                $manifests
     * @param array<string, array{id: int, name: string, version: string, enabled: bool, grants_applied: bool, installed_at: string|null, updated_at: string|null}> $known
     * @param list<string>                                                                                                                                  $order
     */
    private function collectDisabledDependencies(string $name, array $manifests, array $known, array &$order): void
    {
        if (in_array($name, $order, true)) {
            return;
        }

        $requires = isset($manifests[$name]) ? $manifests[$name]->requires : [];

        foreach (array_keys($requires) as $dependency) {
            if (! isset($known[$dependency])) {
                throw new ModuleToggleException(lang('Core.toggle.notInstalled', [$dependency]));
            }

            if (! $known[$dependency]['enabled']) {
                $this->collectDisabledDependencies($dependency, $manifests, $known, $order);
            }
        }

        $order[] = $name;
    }

    /**
     * @param array<string, array<string, mixed>> $known
     */
    private function assertInstalled(string $name, array $known): void
    {
        if (! isset($known[$name])) {
            throw new ModuleToggleException(lang('Core.toggle.notInstalled', [$name]));
        }
    }

    /**
     * @return array<string, ModuleManifest>
     */
    private function manifests(): array
    {
        $manifests = [];

        foreach ($this->discovery->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        return $manifests;
    }

    private function setEnabled(int $id, bool $enabled): void
    {
        $this->registry()->update($id, ['enabled' => $enabled ? 1 : 0, 'updated_at' => Time::now()->toDateTimeString()]);
    }

    private function registry(): ModuleModel
    {
        return $this->registry ?? model(ModuleModel::class, false);
    }
}
