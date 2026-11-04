<?php

declare(strict_types=1);

namespace Modules\Core\Registry;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Config\ModuleManifest;

/**
 * The declarations of the manifests that later services use at runtime: settings, dashboard
 * widgets, scheduled tasks, notification types, menu link types, calendars, calendar providers
 * and API resources (`module_declarations` table).
 *
 * `modules:sync` stores them (step 6) so that regular requests never load the manifests. Readers
 * see only the declarations of enabled modules, except settings (their defaults stay readable).
 * The services that use them (widgets, tasks, notifications…) arrive in later sessions.
 */
final class DeclarationRegistry
{
    public const KINDS = ['setting', 'widget', 'task', 'notification', 'menuLink', 'calendar', 'calendarProvider', 'api'];

    /** Kinds declared as maps: their keys are qualified with the module ("News.perPage"). */
    public const MAP_KINDS = ['setting', 'calendar', 'api'];

    /** Cache scope of each kind. */
    private const SCOPES = ['setting' => 'settings', 'widget' => 'widgets'];

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly CoreCache $cache = new CoreCache(),
        private readonly ModuleState $state = new ModuleState(),
    ) {
    }

    /**
     * The declarations of a set of manifests.
     *
     * @param array<string, ModuleManifest> $manifests
     *
     * @return array<string, array{module: string, kind: string, key: string, definition: array<array-key, mixed>}> "<module>|<kind>|<key>" => declaration
     */
    public function build(array $manifests): array
    {
        $declarations = [];

        foreach ($manifests as $module => $manifest) {
            $lists = [
                'widget'           => $manifest->widgets,
                'task'             => $manifest->tasks,
                'notification'     => $manifest->notifications,
                'menuLink'         => $manifest->menuLinks,
                'calendarProvider' => $manifest->calendarProviders,
            ];
            $maps = [
                'setting'  => $manifest->settings,
                'calendar' => $manifest->calendars,
                'api'      => $manifest->api,
            ];

            foreach ($maps as $kind => $map) {
                foreach ($map as $key => $definition) {
                    $declarations["{$module}|{$kind}|{$key}"] = ['module' => $module, 'kind' => $kind, 'key' => (string) $key, 'definition' => $definition];
                }
            }

            foreach ($lists as $kind => $classes) {
                foreach ($classes as $class) {
                    $class = ltrim($class, '\\');
                    $declarations["{$module}|{$kind}|{$class}"] = ['module' => $module, 'kind' => $kind, 'key' => $class, 'definition' => []];
                }
            }
        }

        return $declarations;
    }

    /**
     * Stores the declarations: inserts new ones, updates changed ones and deletes the rest.
     *
     * @param array<string, array{module: string, kind: string, key: string, definition: array<array-key, mixed>}> $declarations
     *
     * @return bool Whether anything changed
     */
    public function store(array $declarations): bool
    {
        $table   = $this->db()->table('module_declarations');
        $now     = Time::now()->toDateTimeString();
        $changed = false;
        $stored  = [];

        foreach ($table->get()->getResultArray() as $row) {
            $stored["{$row['module']}|{$row['kind']}|{$row['key']}"] = $row;
        }

        foreach ($declarations as $id => $declaration) {
            $json = json_encode($declaration['definition'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! isset($stored[$id])) {
                $this->db()->table('module_declarations')->insert([
                    'module'     => $declaration['module'],
                    'kind'       => $declaration['kind'],
                    'key'        => $declaration['key'],
                    'definition' => $json,
                    'updated_at' => $now,
                ]);
                $changed = true;
            } elseif ($stored[$id]['definition'] !== $json) {
                $this->db()->table('module_declarations')->where('id', $stored[$id]['id'])->update(['definition' => $json, 'updated_at' => $now]);
                $changed = true;
            }
        }

        $removed = array_diff_key($stored, $declarations);

        if ($removed !== []) {
            $this->db()->table('module_declarations')->whereIn('id', array_column($removed, 'id'))->delete();
            $changed = true;
        }

        return $changed;
    }

    /**
     * Declarations of one kind, of enabled modules only (settings: of every module). Keys are
     * class names, or "<Module>.<key>" for settings, calendars and API resources.
     *
     * @return array<string, array{module: string, definition: array<array-key, mixed>}> key => declaration
     */
    public function all(string $kind): array
    {
        $declarations = $this->cache->remember(self::SCOPES[$kind] ?? 'modules', 'declarations.' . $kind, function () use ($kind): ?array {
            try {
                $rows = $this->db()->table('module_declarations')->where('kind', $kind)->orderBy('module')->orderBy('id')->get()->getResultArray();
            } catch (\Throwable) {
                return null; // not installed yet: do not cache
            }

            $result = [];

            foreach ($rows as $row) {
                $definition = json_decode((string) $row['definition'], true);
                $key        = in_array($kind, self::MAP_KINDS, true) ? $row['module'] . '.' . $row['key'] : (string) $row['key'];

                $result[$key] = ['module' => (string) $row['module'], 'definition' => is_array($definition) ? $definition : []];
            }

            return $result;
        });

        if ($declarations === null) {
            $this->cache->invalidate(self::SCOPES[$kind] ?? 'modules');

            return [];
        }

        if ($kind === 'setting') {
            return $declarations;
        }

        $disabled = $this->state->disabled();

        return array_filter($declarations, static fn (array $d): bool => ! in_array($d['module'], $disabled, true));
    }

    /** @return list<string> Widget classes of enabled modules */
    public function widgets(): array
    {
        return array_keys($this->all('widget'));
    }

    /** @return list<string> Scheduled task classes of enabled modules */
    public function tasks(): array
    {
        return array_keys($this->all('task'));
    }

    /** @return list<string> Notification type classes of enabled modules */
    public function notificationTypes(): array
    {
        return array_keys($this->all('notification'));
    }

    /** @return list<string> Menu link type classes of enabled modules */
    public function menuLinkTypes(): array
    {
        return array_keys($this->all('menuLink'));
    }

    /** @return list<string> Calendar provider classes of enabled modules */
    public function calendarProviders(): array
    {
        return array_keys($this->all('calendarProvider'));
    }

    /** @return array<string, array{module: string, definition: array<array-key, mixed>}> Calendars of enabled modules */
    public function calendars(): array
    {
        return $this->all('calendar');
    }

    /** @return array<string, array{module: string, definition: array<array-key, mixed>}> API resources of enabled modules */
    public function apiResources(): array
    {
        return $this->all('api');
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
