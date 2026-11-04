<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

use CodeIgniter\Settings\Handlers\BaseHandler;

/**
 * Read-only codeigniter4/settings handler, placed after the database handler, that answers with
 * the manifest default of a declared module setting (`setting('News.perPage')`) when no value
 * has been saved. A real config class property with the same name (e.g. `Config\Auth`) keeps
 * priority, so file configuration and `.env` still set the defaults of those settings.
 */
final class ManifestDefaultsHandler extends BaseHandler
{
    public function has(string $class, string $property, ?string $context = null): bool
    {
        if (class_exists($class) && property_exists($class, $property)) {
            return false;
        }

        return isset($this->definitions()[$this->name($class, $property)]);
    }

    public function get(string $class, string $property, ?string $context = null): mixed
    {
        return ($this->definitions()[$this->name($class, $property)] ?? null)?->default;
    }

    private function name(string $class, string $property): string
    {
        $pos = strrpos($class, '\\');

        return ($pos === false ? $class : substr($class, $pos + 1)) . '.' . $property;
    }

    /**
     * Read from the "settings" cache scope, so a sync is seen at once.
     *
     * @return array<string, SettingDefinition>
     */
    private function definitions(): array
    {
        return (new SettingsRegistry())->all();
    }
}
