<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Modules\Core\Settings\SettingCaster;
use Modules\Core\Settings\SettingDefinition;
use Modules\Core\Settings\SettingsRegistry;
use Modules\Settings\Forms\Field;
use Throwable;

/**
 * Reads and stores the settings declared by the manifests for the Settings screens.
 *
 * - A setting whose `group` is `security` or starts with `security.` belongs to
 *   Settings › Security; the others to their module's tab.
 * - The value a setting falls back to (`baseline()`) is the file configuration's property when
 *   a `Config\<Module>` class has one (read from a new instance, `.env` included), otherwise the
 *   manifest default. The screens only store overrides: a value equal to it is forgotten.
 */
final class SettingsEditor
{
    public function __construct(private readonly SettingsRegistry $registry = new SettingsRegistry())
    {
    }

    public static function isSecurity(SettingDefinition $definition): bool
    {
        return $definition->group === 'security' || str_starts_with((string) $definition->group, 'security.');
    }

    /**
     * Modules that have a tab (settings outside the security groups): tab slug => module.
     *
     * @return array<string, string>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->registry->all() as $definition) {
            if (! self::isSecurity($definition)) {
                $modules[strtolower($definition->module)] = $definition->module;
            }
        }

        // The Core first, then by name.
        uksort($modules, static fn (string $a, string $b): int => [$a !== 'core', $a] <=> [$b !== 'core', $b]);

        return $modules;
    }

    /**
     * The fields of a module's tab, by group.
     *
     * @return array<string, list<Field>> group ('' for none) => fields
     */
    public function moduleFields(string $module, bool $readOnly = false): array
    {
        $groups = [];

        foreach ($this->registry->forModule($module) as $definition) {
            if (! self::isSecurity($definition)) {
                $groups[(string) $definition->group][] = $this->field($definition, $readOnly);
            }
        }

        return $groups;
    }

    /**
     * The fields of Settings › Security, by module and sub-group (`security.<sub>`).
     *
     * @return array<string, array<string, list<Field>>> module => sub-group => fields
     */
    public function securityFields(): array
    {
        $modules = [];

        foreach ($this->registry->all() as $definition) {
            if (self::isSecurity($definition)) {
                $sub                                  = substr((string) $definition->group, strlen('security.')) ?: '';
                $modules[$definition->module][$sub][] = $this->field($definition);
            }
        }

        uksort($modules, static fn (string $a, string $b): int => [$a !== 'Core', $a] <=> [$b !== 'Core', $b]);

        return $modules;
    }

    public function field(SettingDefinition $definition, bool $readOnly = false): Field
    {
        return Field::fromDefinition($definition, $this->current($definition), $this->baseline($definition), $readOnly);
    }

    /** The value in use: stored, else the baseline (codeigniter4/settings resolves both). */
    public function current(SettingDefinition $definition): mixed
    {
        try {
            $value = service('settings')->get($definition->name());
        } catch (Throwable) {
            $value = null; // not installed yet
        }

        return $value === null ? $this->baseline($definition) : SettingCaster::cast($definition->type, $value);
    }

    /** The value used when nothing is stored. */
    public function baseline(SettingDefinition $definition): mixed
    {
        $configured = $this->configProperty($definition);

        return $configured === null ? $definition->default : SettingCaster::cast($definition->type, $configured[0]);
    }

    /**
     * Stores the values that changed (forgetting those equal to the baseline).
     *
     * @param list<Field>          $fields
     * @param array<string, mixed> $values setting => typed value (FormGenerator::validate())
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>} the changed settings
     */
    public function save(array $fields, array $values, ?string $context = null): array
    {
        $settings = service('settings');
        $before   = [];
        $after    = [];

        foreach ($fields as $field) {
            if ($field->readOnly || ! array_key_exists($field->setting, $values)) {
                continue;
            }

            $value = $values[$field->setting];

            if (Field::text($field->type, $value) === $field->formValue()) {
                continue;
            }

            if (Field::text($field->type, $value) === $field->defaultText()) {
                $settings->forget($field->setting, $context);
            } else {
                $settings->set($field->setting, $value, $context);
            }

            $before[$field->setting] = $field->value;
            $after[$field->setting]  = $value;
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * `[value]` of the file configuration's property, null when `Config\<Module>` has none.
     *
     * @return array{0: mixed}|null
     */
    private function configProperty(SettingDefinition $definition): ?array
    {
        $class = 'Config\\' . $definition->module;

        if (! class_exists($class) || ! property_exists($class, $definition->key)) {
            return null;
        }

        return [(new $class())->{$definition->key}];
    }
}
