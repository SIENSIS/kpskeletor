<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use Config\Database;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Menus\MenuDefinitions;
use Modules\Core\Menus\MenuLinkType;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\DiscoveredModule;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleMigrator;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\SyncReport;
use Modules\Core\Permissions\ShieldMatrix;
use Modules\Core\Theme\ThemeManager;
use Modules\Core\Theme\ThemeRegistry;
use Modules\Core\Widgets\WidgetInterface;
use Throwable;

/**
 * Removes a module or a theme (`modules:uninstall`, `themes:uninstall`, pending uninstalls;
 * docs/installing-modules.md).
 *
 * A module: backup, maintenance mode, disable, roll back its migrations (unless `keepData`), the
 * calendar entries (`service('calendar')->purgeModule()`), the `moduleUninstalling` event (other
 * modules remove what they keep about it), then its
 * permissions and every grant naming them (groups and users), settings, scheduled tasks,
 * notification preferences, demo records, declarations, registry row, its folder and, through
 * `modules:sync`, its namespace and published assets. Core modules and modules that other
 * installed modules require cannot be uninstalled.
 *
 * A theme: never the active theme of an area, the fallback, nor a parent of another theme; its
 * folder, published assets and option values are removed.
 */
final class Uninstaller
{
    private readonly string $modulesRoot;

    private readonly string $themesRoot;

    /** @var Closure(string, string): void */
    private Closure $say;

    /**
     * @param (Closure(): SyncReport)|null                         $sync Runs `modules:sync`
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly Safeguards $safeguards = new Safeguards(),
        private readonly ?Closure $sync = null,
        private readonly ModuleMigrator $migrator = new ModuleMigrator(),
        ?string $modulesRoot = null,
        ?string $themesRoot = null,
        private readonly ?BaseConnection $db = null,
    ) {
        $this->modulesRoot = rtrim($modulesRoot ?? ROOTPATH . 'modules', '/');
        $this->themesRoot  = rtrim($themesRoot ?? (new ThemeRegistry())->root(), '/');
        $this->say         = static function (string $line, string $color = ''): void {
        };
    }

    /**
     * @param Closure(string, string): void $say
     */
    public function onProgress(Closure $say): self
    {
        $this->say = $say;

        return $this;
    }

    /**
     * Why a module cannot be uninstalled (empty: it can).
     *
     * @return list<string>
     */
    public function moduleProblems(string $name, bool $checkWritable = true): array
    {
        if ($name === 'Core' || in_array($name, ModuleToggler::CORE_MODULES, true)) {
            return [lang('Core.packages.uninstallCore', [$name])];
        }

        $modules = $this->discovery()->discover();
        $known   = $this->registry()->allByName();

        if (! isset($modules[$name]) && ! isset($known[$name])) {
            return [lang('Core.toggle.notInstalled', [$name])];
        }

        $dependants = [];

        foreach ($modules as $other => $module) {
            if ($other !== $name && $module->manifest !== null && isset($module->manifest->requires[$name])) {
                $dependants[] = $other;
            }
        }

        if ($dependants !== []) {
            return [lang('Core.packages.uninstallDependants', [$name, implode(', ', $dependants)])];
        }

        if ($checkWritable && isset($modules[$name]) && ! is_writable(dirname($modules[$name]->path))) {
            return [lang('Core.packages.notWritable', [dirname($modules[$name]->path)])];
        }

        return [];
    }

    /**
     * @param array{keepData?: bool, backup?: bool, password?: string|null, userId?: int|null} $options
     *
     * @return list<string> Notes of the modules that removed their data about it
     *
     * @throws PackageRefused
     */
    public function uninstallModule(string $name, array $options = []): array
    {
        $problems = $this->moduleProblems($name);

        if ($problems !== []) {
            throw new PackageRefused(implode("\n", $problems));
        }

        $keepData = $options['keepData'] ?? false;
        $module   = $this->discovery()->discover()[$name] ?? null;

        $this->line(lang('Core.packages.step.backup'));
        $backup = $this->safeguards->backup('uninstall ' . $name, $options['password'] ?? null, $options['backup'] ?? true, $options['userId'] ?? null);
        $this->line($backup !== null ? lang('Core.packages.step.backupDone', [$backup]) : lang('Core.packages.step.noBackup'), $backup !== null ? 'green' : 'yellow');
        $this->line(lang('Core.packages.step.maintenance'));
        $this->safeguards->enterMaintenance();

        try {
            // What other modules must forget, read while the module's classes still load.
            $event = new ModuleUninstalling($name, $keepData, $this->permissionsOf($name), ...$this->declared($module));

            $known = $this->registry()->allByName();

            if (($known[$name]['enabled'] ?? false) === true) {
                $this->line(lang('Core.packages.step.disable'));
                $this->registry()->update($known[$name]['id'], ['enabled' => 0]);
                (new CacheInvalidator())->invalidate();
            }

            if (! $keepData && $module !== null) {
                $this->line(lang('Core.packages.step.migrations'));
                $count = $this->migrator->rollback($module->namespace);
                $this->line(lang('Core.packages.step.rolledBack', [$count]));
            }

            try {
                // The module's calendar entries (Core calendar contract, D-25: purgeModule()).
                if (! $keepData && ($count = service('calendar')->purgeModule($name)) > 0) {
                    $event->note(lang('Core.packages.calendarPurged', [$count]));
                }

                Events::trigger(ModuleUninstalling::EVENT, $event);
            } catch (Throwable $e) {
                $event->note(lang('Core.packages.listenerFailed', [$e->getMessage()]));
            }

            foreach ($event->notes() as $note) {
                $this->line('  ' . $note);
            }

            $this->line(lang('Core.packages.step.cleanup'));
            $this->forget($name, $event->permissions, $keepData);

            if ($module !== null) {
                $this->line(lang('Core.packages.step.delete', ['modules/' . $name]));
                $this->deleteFolder($module->path);
            }

            $this->line(lang('Core.packages.step.sync'));
            $this->runSync();
            (new ShieldMatrix())->write();
        } catch (Throwable $e) {
            security_log('Core', 'package_failed', ['resource' => 'modules', 'summary' => mb_substr('uninstall ' . $name . ': ' . $e->getMessage(), 0, 250), 'changes' => ['backup' => $backup]]);

            throw new PackageRefused(lang('Core.packages.uninstallFailed', [$name, $e->getMessage(), $backup ?? '—']), 0, $e);
        } finally {
            $this->safeguards->leaveMaintenance();
        }

        activity_log('Core', 'module_uninstalled', ['resource' => 'modules', 'summary' => $name, 'changes' => ['keepData' => $keepData, 'backup' => $backup]]);
        $this->line(lang('Core.packages.step.uninstalled', [$name]), 'green');

        return $event->notes();
    }

    /**
     * Why a theme cannot be uninstalled (empty: it can).
     *
     * @return list<string>
     */
    public function themeProblems(string $slug, bool $checkWritable = true): array
    {
        $registry = new ThemeRegistry($this->themesRoot);

        if (! $registry->exists($slug)) {
            return [lang('Core.packages.themeMissing', [$slug])];
        }

        $config = config('Theme');
        $active = [
            (string) (setting('Theme.backend') ?? $config->backend),
            (string) (setting('Theme.public') ?? $config->public),
            (string) $config->fallback,
        ];

        if (in_array($slug, $active, true)) {
            return [lang('Core.packages.themeActive', [$slug])];
        }

        foreach ($registry->slugs() as $other) {
            if ($other === $slug) {
                continue;
            }

            try {
                $parent = ManifestReader::string((new ManifestReader())->readFile($registry->path($other) . '/Theme.php')['properties'], 'parent');
            } catch (Throwable) {
                $parent = '';
            }

            if ($parent === $slug) {
                return [lang('Core.packages.themeParent', [$slug, $other])];
            }
        }

        if ($checkWritable && ! is_writable($this->themesRoot)) {
            return [lang('Core.packages.notWritable', [$this->themesRoot])];
        }

        return [];
    }

    /**
     * @param array{backup?: bool, password?: string|null, userId?: int|null} $options
     *
     * @throws PackageRefused
     */
    public function uninstallTheme(string $slug, array $options = []): void
    {
        $problems = $this->themeProblems($slug);

        if ($problems !== []) {
            throw new PackageRefused(implode("\n", $problems));
        }

        $this->line(lang('Core.packages.step.backup'));
        $backup = $this->safeguards->backup('uninstall theme ' . $slug, $options['password'] ?? null, $options['backup'] ?? true, $options['userId'] ?? null);
        $this->line($backup !== null ? lang('Core.packages.step.backupDone', [$backup]) : lang('Core.packages.step.noBackup'), $backup !== null ? 'green' : 'yellow');

        try {
            // Option values are stored as `Theme.<option>` with the context `theme:<slug>`.
            $this->db()->table('settings')->where('class', 'Modules\Core\Config\Theme')->where('context', 'theme:' . $slug)->delete();
            $this->db()->table('settings')->where('class', 'Config\Theme')->where('context', 'theme:' . $slug)->delete();

            $this->line(lang('Core.packages.step.delete', ['themes/' . $slug]));
            $this->deleteFolder($this->themesRoot . '/' . $slug);
            $this->runSync(); // republishes the assets: the theme's public folder goes as an orphan
        } catch (Throwable $e) {
            throw new PackageRefused(lang('Core.packages.uninstallFailed', [$slug, $e->getMessage(), $backup ?? '—']), 0, $e);
        }

        if (function_exists('service')) {
            /** @var ThemeManager $theme */
            $theme = service('theme');
            $theme->reset();
        }

        activity_log('Core', 'theme_uninstalled', ['resource' => 'modules', 'summary' => $slug, 'changes' => ['backup' => $backup]]);
        $this->line(lang('Core.packages.step.uninstalled', [$slug]), 'green');
    }

    /**
     * The module's permission names (stored catalogue).
     *
     * @return list<string>
     */
    private function permissionsOf(string $name): array
    {
        $rows = $this->db()->table('permissions')->select('name')->where('module', $name)->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * Menu keys, link types and widget ids of a module (instances made while its classes load).
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    private function declared(?DiscoveredModule $module): array
    {
        $manifest = $module?->manifest;

        if (! $manifest instanceof ModuleManifest) {
            return [[], [], []];
        }

        $menuKeys  = array_keys(MenuDefinitions::of($manifest));
        $linkTypes = [];
        $widgets   = [];

        foreach ($manifest->menuLinks as $class) {
            try {
                $type = new $class();

                if ($type instanceof MenuLinkType) {
                    $linkTypes[] = $type->key();
                }
            } catch (Throwable) {
                // A broken class has nothing to remove.
            }
        }

        foreach ($manifest->widgets as $class) {
            try {
                $widget = new $class();

                if ($widget instanceof WidgetInterface) {
                    $widgets[] = $widget->id();
                }
            } catch (Throwable) {
            }
        }

        return [array_map('strval', $menuKeys), $linkTypes, $widgets];
    }

    /**
     * Removes what the Core keeps about a module.
     *
     * @param list<string> $permissions
     */
    private function forget(string $name, array $permissions, bool $keepData): void
    {
        $db       = $this->db();
        $prefixes = array_values(array_unique(array_map(static fn (string $permission): string => (string) strstr($permission, '.', true), $permissions)));
        $named    = static function (string $grant) use ($permissions, $prefixes): bool {
            if (in_array($grant, $permissions, true)) {
                return true;
            }

            return str_ends_with($grant, '.*') && in_array(substr($grant, 0, -2), $prefixes, true);
        };

        // Grants of groups and of users that name the module's permissions (or their wildcards).
        foreach (['group_permissions' => 'permission', config('Auth')->tables['permissions_users'] => 'permission'] as $table => $column) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $stale = array_values(array_filter(
                array_map(static fn (array $row): string => (string) $row[$column], $db->table($table)->select($column)->distinct()->get()->getResultArray()),
                $named,
            ));

            if ($stale !== []) {
                $db->table($table)->whereIn($column, $stale)->delete();
            }
        }

        $db->table('permissions')->where('module', $name)->delete();

        // Settings stored as `<Name>.<key>` or under the module's config classes.
        if ($db->tableExists('settings')) {
            $classes = array_values(array_filter(
                array_map(static fn (array $row): string => (string) $row['class'], $db->table('settings')->select('class')->distinct()->get()->getResultArray()),
                static fn (string $class): bool => $class === $name || str_starts_with($class, 'Modules\\' . $name . '\\'),
            ));

            if ($classes !== []) {
                $db->table('settings')->whereIn('class', $classes)->delete();
            }
        }

        $db->table('scheduled_tasks')->where('module', $name)->delete();
        $db->table('module_declarations')->where('module', $name)->delete();

        $types = array_values(array_filter(
            array_map(static fn (array $row): string => (string) $row['type'], $db->table('notification_preferences')->select('type')->distinct()->get()->getResultArray()),
            static fn (string $type): bool => str_starts_with(ltrim($type, '\\'), 'Modules\\' . $name . '\\'),
        ));

        if ($types !== []) {
            $db->table('notification_preferences')->whereIn('type', $types)->delete();
        }

        if (! $keepData) {
            $db->table('notifications')->where('module', $name)->delete();
            $db->table('demo_records')->where('module', $name)->delete();
        }

        $this->registry()->where('name', $name)->delete();
        (new CacheInvalidator())->invalidate();
    }

    private function deleteFolder(string $path): void
    {
        $aside = dirname($path) . '/.' . basename($path) . '.removed-' . bin2hex(random_bytes(4));

        if (! @rename($path, $aside)) {
            throw new PackageRefused(lang('Core.packages.notWritable', [$path]));
        }

        PackageValidator::removeFolder($aside);
    }

    private function runSync(): void
    {
        $this->sync !== null ? ($this->sync)() : (new ModuleSynchronizer())->sync();
    }

    private function discovery(): ModuleDiscovery
    {
        $default = rtrim(ROOTPATH . 'modules', '/');

        return new ModuleDiscovery($this->modulesRoot === $default ? [$default] : [$default, $this->modulesRoot]);
    }

    private function registry(): ModuleModel
    {
        return model(ModuleModel::class, false);
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }

    private function line(string $line, string $color = ''): void
    {
        ($this->say)($line, $color);
    }
}
