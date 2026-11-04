<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

use Modules\Core\Registry\DeclarationRegistry;

/**
 * The settings declared by the manifests (registered by `modules:sync`), namespaced per module:
 * `setting('News.perPage')`. Values are stored by codeigniter4/settings; the manifest default is
 * returned until a value is saved (see ManifestDefaultsHandler).
 */
final class SettingsRegistry
{
    public function __construct(private readonly DeclarationRegistry $declarations = new DeclarationRegistry())
    {
    }

    /**
     * @return array<string, SettingDefinition> "<Module>.<key>" => definition
     */
    public function all(): array
    {
        $definitions = [];

        foreach ($this->declarations->all('setting') as $name => $declaration) {
            $key                = substr($name, strlen($declaration['module']) + 1);
            $definitions[$name] = SettingDefinition::fromArray($declaration['module'], $key, $declaration['definition']);
        }

        return $definitions;
    }

    /**
     * @return array<string, SettingDefinition> key => definition
     */
    public function forModule(string $module): array
    {
        $definitions = [];

        foreach ($this->all() as $definition) {
            if ($definition->module === $module) {
                $definitions[$definition->key] = $definition;
            }
        }

        return $definitions;
    }

    public function find(string $name): ?SettingDefinition
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * The typed value of a declared setting: the stored value cast to its type, or the default.
     */
    public function value(string $name): mixed
    {
        $definition = $this->find($name);
        $value      = setting($name);

        return $definition === null ? $value : SettingCaster::cast($definition->type, $value ?? $definition->default);
    }
}
