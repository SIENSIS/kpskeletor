<?php

declare(strict_types=1);

namespace Modules\Settings\Forms;

use Modules\Core\Settings\SettingCaster;
use Modules\Core\Settings\SettingDefinition;

/**
 * One generated form field: a module setting (`<Module>.<key>`) or a theme option, with its
 * current value, the value it falls back to when not stored (`$default`), its validation rules
 * (the declared ones plus those of its type) and the casting of the posted text.
 */
final class Field
{
    /**
     * @param string                $name    form field name (letters, digits, '-' and '_')
     * @param array<string, string> $options value => language key of a 'select'
     */
    public function __construct(
        public readonly string $name,
        public readonly string $setting,
        public readonly string $type,
        public readonly string $label,
        public readonly mixed $value,
        public readonly mixed $default,
        public readonly string $rules = '',
        public readonly ?string $help = null,
        public readonly array $options = [],
        public readonly bool $readOnly = false,
        public readonly ?string $group = null,
    ) {
    }

    public static function fromDefinition(SettingDefinition $definition, mixed $value, mixed $default, bool $readOnly = false): self
    {
        return new self(
            $definition->module . '-' . $definition->key,
            $definition->name(),
            $definition->type,
            $definition->label,
            $value,
            $default,
            $definition->rules,
            $definition->help,
            self::options($definition->options),
            $readOnly,
            $definition->group,
        );
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @return array<string, string>
     */
    public static function options(array $options): array
    {
        $list = [];

        foreach ($options as $value => $label) {
            $list[(string) $value] = is_string($label) ? $label : (string) $value;
        }

        return $list;
    }

    /**
     * The rules checked on the posted text: `permit_empty` unless declared `required`, the
     * type's own rules, then the declared ones.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $declared = array_values(array_filter(explode('|', $this->rules), static fn (string $r): bool => $r !== ''));
        $rules    = in_array('required', $declared, true) || $this->type === 'bool' ? [] : ['permit_empty'];

        $rules = [...$rules, ...match ($this->type) {
            'int'    => ['integer'],
            'float'  => ['decimal'],
            'bool'   => ['in_list[0,1]'],
            'email'  => ['valid_email'],
            'url'    => ['valid_url_strict[https]'],
            'color'  => ['regex_match[/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/]'],
            'json'   => ['valid_json'],
            'select' => ['in_list[' . implode(',', array_keys($this->options)) . ']'],
            default  => [],
        }];

        return array_values(array_unique([...$rules, ...array_diff($declared, ['permit_empty'])]));
    }

    /**
     * The typed value of the posted text (a missing checkbox is false).
     */
    public function cast(mixed $posted): mixed
    {
        if ($this->type === 'bool') {
            return $posted === '1' || $posted === 1 || $posted === true;
        }

        $posted = is_scalar($posted) ? (string) $posted : '';

        if ($this->type === 'json') {
            $posted = trim($posted) === '' ? '[]' : $posted;
        }

        if (in_array($this->type, ['string', 'text', 'email', 'url', 'select', 'color'], true)) {
            $posted = $this->type === 'text' ? str_replace("\r\n", "\n", $posted) : trim($posted);
        }

        return SettingCaster::cast($this->type, $posted);
    }

    /**
     * The value as the form shows it: lists one per line, JSON pretty-printed, booleans '1'/'0'.
     */
    public static function text(string $type, mixed $value): string
    {
        return match (true) {
            $type === 'bool'                       => $value === true ? '1' : '0',
            $type === 'list' && is_array($value)   => implode("\n", array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $value)),
            $type === 'json'                       => $value === [] || $value === null ? '' : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            is_scalar($value)                      => (string) $value,
            default                                => '',
        };
    }

    public function formValue(): string
    {
        return self::text($this->type, $this->value);
    }

    public function defaultText(): string
    {
        return self::text($this->type, $this->default);
    }

    /** Whether the value differs from what applies when nothing is stored. */
    public function isOverridden(): bool
    {
        return $this->formValue() !== $this->defaultText();
    }
}
