<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Closure;
use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Permissions\CatalogueBuilder;
use Modules\Core\Permissions\DefaultGrants;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;
use Modules\Core\Registry\ApiRouteChecker;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Theme\ThemeValidator;

/**
 * `modules:sync` (see "Module lifecycle"): discovers and validates the modules, rewrites the
 * generated namespaces file, runs migrations (packages first, then modules in dependency order),
 * seeds new modules, upserts the registry, the permission catalogue, the default grants (first
 * install only) and the declarations (settings, widgets, tasks…), optionally loads demo data and
 * invalidates the derived caches. Idempotent: a second run changes nothing.
 *
 * Every check (manifests, dependencies, duplicate permissions, `$api` routes) runs before
 * anything is written, the themes included (ThemeValidator). Step 7 publishes the assets of the
 * Core, the modules and the themes and writes the asset manifest (AssetPublisher).
 */
final class ModuleSynchronizer
{
    public function __construct(
        private readonly ModuleDiscovery $discovery = new ModuleDiscovery(),
        private readonly NamespaceFile $namespaceFile = new NamespaceFile(),
        private readonly ModuleMigrator $migrator = new ModuleMigrator(),
        private readonly CacheInvalidator $caches = new CacheInvalidator(),
        private readonly ?ModuleModel $registry = null,
        private readonly PermissionCatalogue $permissions = new PermissionCatalogue(),
        private readonly DeclarationRegistry $declarations = new DeclarationRegistry(),
        private readonly ?DemoDataManager $demo = null,
        private readonly ?ThemeValidator $themes = null,
        private readonly ?AssetPublisher $assets = null,
    ) {
    }

    /**
     * @param bool                               $prune        Remove permissions no manifest generates any more…
     * @param Closure(list<string>): bool|null   $confirmPrune …after this confirmation (required with $prune)
     * @param list<string>|null                  $demo         Load the demo data of these modules (null: none; []: every enabled module with a demo seeder)
     *
     * @throws SyncFailedException when a manifest or a dependency is invalid (nothing is changed)
     */
    public function sync(bool $prune = false, ?Closure $confirmPrune = null, ?array $demo = null): SyncReport
    {
        $report = new SyncReport();

        // 1. Discover, validate, check dependencies, permissions and API routes.
        $modules = $this->discovery->discover();
        [$manifests, $errors] = $this->validate($modules);

        $builder   = new CatalogueBuilder();
        $catalogue = $builder->build($manifests);
        $errors    = [...$errors, ...$builder->errors()];

        if ($errors === []) {
            $errors = (new ApiRouteChecker())->check($manifests);
        }

        $errors = [...$errors, ...($this->themes ?? new ThemeValidator())->validateAll()];

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

        // 4. Permission catalogue; stale permissions are removed only with --prune + confirmation.
        $result                     = $this->permissions->upsert($catalogue);
        $report->permissionsAdded   = $result['added'];
        $report->permissionsUpdated = $result['updated'];
        $report->stalePermissions   = $result['stale'];

        if ($prune && $result['stale'] !== [] && $confirmPrune !== null && $confirmPrune($result['stale'])) {
            $this->permissions->prune($result['stale']);
            $report->pruned           = $result['stale'];
            $report->stalePermissions = [];
        }

        // 5. Default grants (completed with the implied permissions), only once per module.
        $grants = new DefaultGrants();

        foreach ($registry->allByName() as $name => $row) {
            if ($row['grants_applied'] || ! isset($manifests[$name])) {
                continue;
            }

            $report->grantsApplied[$name] = $this->permissions->grant($grants->expand($manifests[$name]));
            $registry->update($row['id'], ['grants_applied' => 1]);
        }

        // 6. Settings definitions, widgets, tasks, notification types, menu link types, calendars, API.
        $ordered = [];

        foreach ($report->order as $name) {
            $ordered[$name] = $manifests[$name];
        }

        $report->declarationsChanged = $this->declarations->store($this->declarations->build($ordered));

        // 7. Assets of the Core, the modules and the themes, and the asset manifest.
        $report->assets = ($this->assets ?? new AssetPublisher())->publish();

        // 8. Invalidate the caches derived from the manifests.
        if ($report->hasChanges()) {
            $this->caches->invalidate();
        }

        // 9. The reserved groups and Shield's groups, permissions and group matrix, computed
        //    from the `groups` table, the catalogue and the grants.
        $groups = new GroupRegistry($this->permissions);
        $groups->ensureReserved();
        $groups->mirror();
        (new ShieldMatrix($this->permissions))->write();

        // Demo data (optional): after the caches, so the registry is current.
        if ($demo !== null) {
            $manager = $this->demo ?? new DemoDataManager();
            $manager->assertAllowed();

            foreach ($demo === [] ? $manager->modulesWithDemo() : $demo as $module) {
                if ($manager->load($module)) {
                    $report->demoLoaded[] = $module;
                }
            }

            $report->demoSecrets = $manager->takeSecrets();
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
