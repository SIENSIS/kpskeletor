<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Models\ModuleModel;

/**
 * `modules:sync`: discovers and validates the modules, rewrites the generated namespaces file,
 * runs migrations (packages first, then modules in dependency order), seeds new modules, upserts
 * the registry and invalidates the derived caches. Idempotent: a second run changes nothing.
 *
 * Steps 4–7 (permissions, grants, settings/widgets/tasks registration, assets) are added by
 * later sessions.
 */
final class ModuleSynchronizer
{
    public function __construct(
        private readonly ModuleDiscovery $discovery = new ModuleDiscovery(),
        private readonly NamespaceFile $namespaceFile = new NamespaceFile(),
        private readonly ModuleMigrator $migrator = new ModuleMigrator(),
        private readonly CacheInvalidator $caches = new CacheInvalidator(),
        private readonly ?ModuleModel $registry = null,
    ) {
    }

    /**
     * @throws SyncFailedException when a manifest or a dependency is invalid (nothing is changed)
     */
    public function sync(): SyncReport
    {
        $report = new SyncReport();

        // 1. Discover, validate, check dependencies.
        $modules = $this->discovery->discover();
        [$manifests, $errors] = $this->validate($modules);

        if ($errors !== []) {
            throw new SyncFailedException($errors);
        }

        $report->order = (new DependencyResolver())->order($manifests);

        $folders = [];

        foreach ($modules as $name => $module) {
            $folders[$name] = $module->path;
        }

        $report->namespacesChanged = $this->namespaceFile->write($folders);

        // 2. Migrations: third-party packages as they ship, then modules in dependency order.
        foreach (ModuleMigrator::PACKAGE_NAMESPACES as $namespace) {
            $report->migrations[$namespace] = $this->migrator->migrate($namespace);
        }

        foreach ($report->order as $name) {
            $report->migrations[$modules[$name]->namespace] = $this->migrator->migrate($modules[$name]->namespace);
        }

        // 2b/3. Seed on first install, then upsert the registry.
        $registry = $this->registry ?? model(ModuleModel::class, false);
        $known    = $registry->allByName();
        $now      = Time::now()->toDateTimeString();

        foreach ($report->order as $name) {
            $manifest = $manifests[$name];

            if (! isset($known[$name])) {
                if ($this->seed($name)) {
                    $report->seeded[] = $name;
                }

                $registry->insert([
                    'name'         => $name,
                    'version'      => $manifest->version,
                    'enabled'      => 1,
                    'installed_at' => $now,
                    'updated_at'   => $now,
                ]);
                $report->installed[$name] = $manifest->version;
            } elseif ($known[$name]['version'] !== $manifest->version) {
                $registry->update($known[$name]['id'], ['version' => $manifest->version, 'updated_at' => $now]);
                $report->upgraded[$name] = [$known[$name]['version'], $manifest->version];
            }
        }

        $report->missing = array_values(array_diff(array_keys($known), array_keys($modules)));

        // 8. Invalidate the caches derived from the manifests.
        if ($report->hasChanges()) {
            $this->caches->invalidate();
        }

        return $report;
    }

    /**
     * @param array<string, DiscoveredModule> $modules
     *
     * @return array{0: array<string, ModuleManifest>, 1: list<string>}
     */
    private function validate(array $modules): array
    {
        $manifests = [];
        $errors    = [];
        $prefixes  = [];

        if (! isset($modules['Core'])) {
            $errors[] = lang('Core.sync.coreMissing');
        }

        foreach ($modules as $name => $module) {
            array_push($errors, ...$module->errors);

            if ($module->manifest === null) {
                continue;
            }

            $manifests[$name] = $module->manifest;

            // Permission prefixes must be unique across modules (resource 'name' overrides).
            foreach (array_keys($module->manifest->resources) as $resource) {
                $prefix = $module->manifest->permissionPrefix((string) $resource);

                if (isset($prefixes[$prefix]) && $prefixes[$prefix] !== $name) {
                    $errors[] = lang('Core.manifest.error', [$name, "\$resources['{$resource}']", lang('Core.manifest.duplicatePrefix', [$prefix, $prefixes[$prefix]])]);
                }

                $prefixes[$prefix] = $name;
            }
        }

        $resolver = new DependencyResolver();
        $resolver->order($manifests);

        return [$manifests, array_merge($errors, $resolver->errors())];
    }

    private function seed(string $module): bool
    {
        $class = "Modules\\{$module}\\Database\\Seeds\\{$module}Seeder";

        if (! class_exists($class)) {
            return false;
        }

        $seeder = Database::seeder();
        $seeder->setSilent(true);
        $seeder->call($class);

        return true;
    }
}
