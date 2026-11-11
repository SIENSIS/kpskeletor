<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Modules\Core\Config\ModuleManifest;
use Modules\Core\Settings\SettingOptionsProvider;
use Modules\Core\Widgets\WidgetInterface;

/**
 * Validates a module manifest. Every error names the module and the manifest key
 * (e.g. "News: $resources['posts']['type'] must be one of: crud, page").
 */
final class ManifestValidator
{
    /** Module names that would clash with generated permission names. */
    public const RESERVED_NAMES = ['Security', 'Update'];

    /**
     * File names CodeIgniter gives a special meaning to: a module config class with one of these
     * names (`Config/<Name>.php` extending BaseConfig) is refused.
     */
    public const RESERVED_CONFIG_FILES = ['Events', 'Routes', 'Registrar', 'Filters', 'Modules', 'Services'];

    /** kpcrudy operations generated for a 'crud' resource. */
    public const CRUD_OPERATIONS = ['list', 'show', 'add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'];

    public const RESOURCE_TYPES = ['crud', 'page'];

    public const SETTING_TYPES = ['string', 'text', 'int', 'float', 'bool', 'select', 'email', 'url', 'color', 'json', 'list'];

    public const MENU_KEYS = ['resource', 'route', 'permission', 'label', 'icon', 'order', 'zone', 'parent'];

    public const CSP_DIRECTIVES = [
        'default-src', 'script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'media-src',
        'object-src', 'frame-src', 'child-src', 'worker-src', 'manifest-src', 'form-action', 'frame-ancestors',
    ];

    /** CSP sources a module may never add: they would defeat the strict policy. */
    public const FORBIDDEN_CSP_SOURCES = ["'unsafe-inline'", "'unsafe-eval'", "'unsafe-hashes'", '*', 'http:', 'data:'];

    /** @var list<string> */
    private array $errors = [];

    private string $module = '';

    /**
     * The manifest's public properties as raw values: the PHPDoc types of ModuleManifest are what
     * this class checks, so they are not trusted here.
     *
     * @var array<string, mixed>
     */
    private array $raw = [];

    /**
     * @param string $path Module folder (e.g. ROOTPATH . 'modules/News')
     *
     * @return list<string> Error messages; empty when the manifest is valid
     */
    public function validate(ModuleManifest $manifest, string $path): array
    {
        $this->errors = [];
        $this->raw    = get_object_vars($manifest);
        $folder       = basename(rtrim($path, '/\\'));
        $name         = $this->raw['name'] ?? '';
        $this->module = is_string($name) && $name !== '' ? $name : $folder;

        $this->checkName($folder);
        $this->checkVersion();
        $this->checkRequires();
        $this->checkRepository();
        $this->checkResources();
        $this->checkDefaultGrants();
        $this->checkMenu();
        $this->checkClassList('menuLinks');
        $this->checkSettings();
        $this->checkClassList('widgets', WidgetInterface::class);
        $this->checkClassList('tasks');
        $this->checkClassList('notifications');
        $this->checkBackup();
        $this->checkCalendars();
        $this->checkClassList('calendarProviders');
        $this->checkApi();
        $this->checkCsp();
        $this->checkOutboundHosts();
        $this->checkConfigFiles($path);

        return $this->errors;
    }

    private function checkName(string $folder): void
    {
        $name = $this->raw['name'] ?? null;

        if (! is_string($name) || $name === '') {
            $this->error('name', 'required');

            return;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->error('name', 'nameFormat');
        }

        if ($name !== $folder) {
            $this->error('name', 'nameFolder', [$folder]);
        }

        if (in_array($name, self::RESERVED_NAMES, true)) {
            $this->error('name', 'nameReserved', [$name]);
        }
    }

    private function checkVersion(): void
    {
        $version = $this->raw['version'] ?? null;

        if (! is_string($version) || ! SemVer::isVersion($version)) {
            $this->error('version', 'version');
        }
    }

    private function checkRequires(): void
    {
        foreach ($this->map('requires') as $module => $constraint) {
            $key = "requires['{$module}']";

            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', (string) $module) !== 1) {
                $this->error($key, 'nameFormat');
            }

            if (! is_string($constraint) || ! SemVer::isConstraint($constraint)) {
                $this->error($key, 'constraint', [is_string($constraint) ? $constraint : get_debug_type($constraint)]);
            }
        }
    }

    private function checkRepository(): void
    {
        $repository = $this->raw['repository'] ?? null;

        if ($repository === null) {
            return;
        }

        $valid = is_string($repository) && (
            preg_match('#^github:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) === 1
            || preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?/[^\s]+$#', $repository) === 1
        );

        if (! $valid) {
            $this->error('repository', 'repository', [is_string($repository) ? $repository : get_debug_type($repository)]);
        }
    }

    private function checkResources(): void
    {
        $allowed   = ['label', 'type', 'except', 'extra', 'name'];
        $resources = $this->map('resources');

        foreach ($resources as $resource => $definition) {
            $key = "resources['{$resource}']";

            if (preg_match('/^[a-z][a-z0-9]*$/', (string) $resource) !== 1) {
                $this->error($key, 'resourceKey', [(string) $resource]);

                continue;
            }

            if (! is_array($definition)) {
                $this->error($key, 'mustBe', ['array']);

                continue;
            }

            foreach (array_keys($definition) as $k) {
                if (! in_array($k, $allowed, true)) {
                    $this->error($key, 'unknownKey', [(string) $k]);
                }
            }

            $this->requireString($definition, 'label', "{$key}['label']");

            $type = $definition['type'] ?? null;

            if (! in_array($type, self::RESOURCE_TYPES, true)) {
                $this->error("{$key}['type']", 'oneOf', [implode(', ', self::RESOURCE_TYPES)]);
            }

            if (array_key_exists('except', $definition)) {
                $except = $definition['except'];

                if ($type !== 'crud') {
                    $this->error("{$key}['except']", 'exceptCrudOnly');
                } elseif (! $this->isStringList($except)) {
                    $this->error("{$key}['except']", 'mustBe', ['list<string>']);
                } elseif (array_diff($except, self::CRUD_OPERATIONS) !== []) {
                    $this->error("{$key}['except']", 'oneOf', [implode(', ', self::CRUD_OPERATIONS)]);
                }
            }

            if (array_key_exists('extra', $definition)) {
                $extra = $definition['extra'];

                if (! is_array($extra)) {
                    $this->error("{$key}['extra']", 'mustBe', ['array<string, string>']);
                } else {
                    foreach ($extra as $operation => $label) {
                        if (preg_match('/^[a-z][a-zA-Z0-9]*$/', (string) $operation) !== 1) {
                            $this->error("{$key}['extra']", 'operation', [(string) $operation]);
                        }

                        if (! is_string($label) || $label === '') {
                            $this->error("{$key}['extra']['{$operation}']", 'required');
                        }
                    }
                }
            }

            if (array_key_exists('name', $definition)) {
                $prefix = $definition['name'];

                if (! is_string($prefix) || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $prefix) !== 1) {
                    $this->error("{$key}['name']", 'prefix', [is_string($prefix) ? $prefix : get_debug_type($prefix)]);
                }

                if ($this->module !== 'Core' && count($resources) > 1) {
                    $this->error("{$key}['name']", 'resourceName');
                }
            }
        }
    }

    private function checkDefaultGrants(): void
    {
        $prefixes = [];

        foreach ($this->map('resources') as $resource => $definition) {
            $override   = is_array($definition) ? ($definition['name'] ?? null) : null;
            $prefixes[] = is_string($override) && $override !== '' ? $override : strtolower($this->module) . '-' . $resource;
        }

        foreach ($this->map('defaultGrants') as $group => $permissions) {
            $key = "defaultGrants['{$group}']";

            if (preg_match('/^[a-z][a-z0-9_-]*$/', (string) $group) !== 1) {
                $this->error($key, 'group', [(string) $group]);
            }

            if (! $this->isStringList($permissions)) {
                $this->error($key, 'mustBe', ['list<string>']);

                continue;
            }

            foreach ($permissions as $permission) {
                $parts = explode('.', $permission, 2);

                if (count($parts) !== 2 || ! in_array($parts[0], $prefixes, true)
                    || preg_match('/^(\*|[a-z][a-zA-Z0-9]*)$/', $parts[1]) !== 1) {
                    $this->error($key, 'permission', [$permission]);
                }
            }
        }
    }

    private function checkMenu(): void
    {
        $resources = $this->map('resources');

        foreach ($this->map('menu') as $i => $entry) {
            $key = "menu[{$i}]";

            if (! is_array($entry)) {
                $this->error($key, 'mustBe', ['array']);

                continue;
            }

            foreach (array_keys($entry) as $k) {
                if (! in_array($k, self::MENU_KEYS, true)) {
                    $this->error($key, 'unknownKey', [(string) $k]);
                }
            }

            $this->requireString($entry, 'label', "{$key}['label']");

            if (array_key_exists('resource', $entry)) {
                $resource = $entry['resource'];

                if (! is_string($resource) || ! array_key_exists($resource, $resources)) {
                    $this->error("{$key}['resource']", 'unknownResource', [is_string($resource) ? $resource : get_debug_type($resource)]);
                }
            } else {
                $this->requireString($entry, 'route', "{$key}['route']");
                $this->requireString($entry, 'permission', "{$key}['permission']");
            }

            if (array_key_exists('order', $entry) && ! is_int($entry['order'])) {
                $this->error("{$key}['order']", 'mustBe', ['int']);
            }

            foreach (['icon', 'zone', 'parent'] as $optional) {
                if (array_key_exists($optional, $entry) && ! is_string($entry[$optional])) {
                    $this->error("{$key}['{$optional}']", 'mustBe', ['string']);
                }
            }
        }
    }

    private function checkSettings(): void
    {
        foreach ($this->map('settings') as $setting => $definition) {
            $key = "settings['{$setting}']";

            if (preg_match('/^[a-z][a-zA-Z0-9]*$/', (string) $setting) !== 1) {
                $this->error($key, 'settingKey', [(string) $setting]);
            }

            if (! is_array($definition)) {
                $this->error($key, 'mustBe', ['array']);

                continue;
            }

            $type = $definition['type'] ?? null;

            if (! in_array($type, self::SETTING_TYPES, true)) {
                $this->error("{$key}['type']", 'oneOf', [implode(', ', self::SETTING_TYPES)]);
            }

            if (! array_key_exists('default', $definition)) {
                $this->error("{$key}['default']", 'required');
            }

            $this->requireString($definition, 'label', "{$key}['label']");

            $provider = $definition['optionsProvider'] ?? null;

            if ($provider !== null && (! is_string($provider) || ! class_exists($provider) || ! is_subclass_of($provider, SettingOptionsProvider::class))) {
                $this->error("{$key}['optionsProvider']", 'mustBe', [SettingOptionsProvider::class]);
            }

            if ($type === 'select' && $provider === null && (! isset($definition['options']) || ! is_array($definition['options']) || $definition['options'] === [])) {
                $this->error("{$key}['options']", 'required');
            }
        }
    }

    private function checkBackup(): void
    {
        foreach ($this->map('backup') as $kind => $paths) {
            $key = "backup['{$kind}']";

            if (! in_array($kind, ['files', 'tables'], true)) {
                $this->error('backup', 'unknownKey', [(string) $kind]);

                continue;
            }

            if (! $this->isStringList($paths)) {
                $this->error($key, 'mustBe', ['list<string>']);

                continue;
            }

            foreach ($paths as $path) {
                if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
                    $this->error($key, 'path', [$path]);
                }
            }
        }
    }

    private function checkCalendars(): void
    {
        foreach ($this->map('calendars') as $calendar => $definition) {
            $key = "calendars['{$calendar}']";

            if (preg_match('/^[a-z][a-z0-9]*$/', (string) $calendar) !== 1) {
                $this->error($key, 'resourceKey', [(string) $calendar]);
            }

            if (! is_array($definition)) {
                $this->error($key, 'mustBe', ['array']);

                continue;
            }

            $this->requireString($definition, 'label', "{$key}['label']");

            $color = $definition['color'] ?? null;

            if (! is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
                $this->error("{$key}['color']", 'color');
            }

            if (array_key_exists('permission', $definition) && ! is_string($definition['permission'])) {
                $this->error("{$key}['permission']", 'mustBe', ['string']);
            }
        }
    }

    private function checkApi(): void
    {
        foreach ($this->map('api') as $resource => $definition) {
            $key = "api['{$resource}']";

            if (! is_array($definition)) {
                $this->error($key, 'mustBe', ['array']);

                continue;
            }

            $path = $definition['path'] ?? null;

            if (! is_string($path) || preg_match('#^[a-z0-9][a-z0-9-]*(?:/[a-z0-9][a-z0-9-]*)*$#', $path) !== 1) {
                $this->error("{$key}['path']", 'path', [is_string($path) ? $path : get_debug_type($path)]);
            }

            $handler = $definition['handler'] ?? null;

            if (! is_string($handler) || preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $handler) !== 1) {
                $this->error("{$key}['handler']", 'handler', [is_string($handler) ? $handler : get_debug_type($handler)]);
            }
        }
    }

    private function checkCsp(): void
    {
        foreach ($this->map('csp') as $directive => $sources) {
            $key = "csp['{$directive}']";

            if (! in_array($directive, self::CSP_DIRECTIVES, true)) {
                $this->error('csp', 'oneOf', [implode(', ', self::CSP_DIRECTIVES)]);

                continue;
            }

            if (! $this->isStringList($sources)) {
                $this->error($key, 'mustBe', ['list<string>']);

                continue;
            }

            foreach ($sources as $source) {
                $valid = preg_match('#^https://(\*\.)?[A-Za-z0-9.-]+(:\d+)?(/[^\s;,]*)?$#', $source) === 1
                    || in_array($source, ["'self'", "'none'", 'blob:'], true);

                if (! $valid || in_array(strtolower($source), self::FORBIDDEN_CSP_SOURCES, true)) {
                    $this->error($key, 'cspSource', [$source]);
                }
            }
        }
    }

    private function checkOutboundHosts(): void
    {
        $hosts = $this->raw['outboundHosts'] ?? [];

        if (! $this->isStringList($hosts)) {
            $this->error('outboundHosts', 'mustBe', ['list<string>']);

            return;
        }

        foreach ($hosts as $host) {
            if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) !== 1) {
                $this->error('outboundHosts', 'host', [$host]);
            }
        }
    }

    private function checkClassList(string $key, ?string $interface = null): void
    {
        $classes = $this->raw[$key] ?? [];

        if (! $this->isStringList($classes)) {
            $this->error($key, 'mustBe', ['list<class-string>']);

            return;
        }

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                $this->error($key, 'classMissing', [$class]);
            } elseif ($interface !== null && ! is_subclass_of($class, $interface)) {
                $this->error($key, 'notWidget', [$class, $interface]);
            }
        }
    }

    /**
     * A manifest property expected to be an array; reports and returns [] when it is not.
     *
     * @return array<array-key, mixed>
     */
    private function map(string $key): array
    {
        $value = $this->raw[$key] ?? [];

        if (! is_array($value)) {
            $this->error($key, 'mustBe', ['array']);

            return [];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private function requireString(array $definition, string $field, string $key): void
    {
        $value = $definition[$field] ?? null;

        if (! is_string($value) || $value === '') {
            $this->error($key, 'required');
        }
    }

    private function checkConfigFiles(string $path): void
    {
        foreach (self::RESERVED_CONFIG_FILES as $name) {
            $file = rtrim($path, '/\\') . "/Config/{$name}.php";

            if (! is_file($file)) {
                continue;
            }

            $code = (string) file_get_contents($file);

            if (preg_match('/class\s+' . $name . '\s+extends\s+\\\\?(?:CodeIgniter\\\\Config\\\\)?BaseConfig\b/', $code) === 1) {
                $this->error("Config/{$name}.php", 'configClass', [$name]);
            }
        }
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private function isStringList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    private function error(string $key, string $message, array $args = []): void
    {
        $this->errors[] = lang('Core.manifest.error', [
            $this->module,
            str_starts_with($key, 'Config/') ? $key : '$' . $key,
            lang('Core.manifest.' . $message, $args),
        ]);
    }
}
