<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * A runtime setting declared in a module manifest (`$settings`).
 */
final class SettingDefinition
{
    /**
     * @param array<array-key, mixed> $options         Fixed choices of a 'select' setting (value => language key)
     * @param string|null             $optionsProvider A SettingOptionsProvider class computing the choices at run time
     */
    public function __construct(
        public readonly string $module,
        public readonly string $key,
        public readonly string $type,
        public readonly mixed $default,
        public readonly string $label,
        public readonly string $rules = '',
        public readonly ?string $help = null,
        public readonly ?string $group = null,
        public readonly array $options = [],
        public readonly ?string $optionsProvider = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    public static function fromArray(string $module, string $key, array $definition): self
    {
        $type = is_string($definition['type'] ?? null) ? $definition['type'] : 'string';

        return new self(
            $module,
            $key,
            $type,
            SettingCaster::cast($type, $definition['default'] ?? null),
            is_string($definition['label'] ?? null) ? $definition['label'] : $module . '.settings.' . $key,
            is_string($definition['rules'] ?? null) ? $definition['rules'] : '',
            is_string($definition['help'] ?? null) ? $definition['help'] : null,
            is_string($definition['group'] ?? null) ? $definition['group'] : null,
            is_array($definition['options'] ?? null) ? $definition['options'] : [],
            is_string($definition['optionsProvider'] ?? null) && $definition['optionsProvider'] !== '' ? $definition['optionsProvider'] : null,
        );
    }

    /**
     * The choices computed by the options provider (value => translated label), or null when the
     * setting has fixed options (or the provider is not a SettingOptionsProvider).
     *
     * @return array<string, string>|null
     */
    public function providedOptions(): ?array
    {
        $class = $this->optionsProvider;

        if ($class === null || ! class_exists($class) || ! is_subclass_of($class, SettingOptionsProvider::class)) {
            return null;
        }

        $options = [];

        foreach ((new $class())->options() as $value => $label) {
            $options[(string) $value] = (string) $label;
        }

        return $options;
    }

    /** "<Module>.<key>", the name used with setting(). */
    public function name(): string
    {
        return $this->module . '.' . $this->key;
    }
}
