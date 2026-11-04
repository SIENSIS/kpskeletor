<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * A runtime setting declared in a module manifest (`$settings`).
 */
final class SettingDefinition
{
    /**
     * @param array<array-key, mixed> $options Choices of a 'select' setting
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
        );
    }

    /** "<Module>.<key>", the name used with setting(). */
    public function name(): string
    {
        return $this->module . '.' . $this->key;
    }
}
