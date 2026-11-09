<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use Modules\Core\Registry\DeclarationRegistry;

/**
 * The options of a widget (`WidgetInterface::settings()`): defaults, values read back from the
 * group layouts (anything unknown or invalid falls back to the default) and the validation of
 * the per-group configuration form.
 *
 * Types: `int` (`min`, `max`), `bool`, `string` (up to 255 characters, one line), `select`
 * (`options`: value => language key) and `menu` (a list of back-office menu entry keys, e.g.
 * `Users.users` or `settings`; shown to each user only when they can open the entry).
 */
final class WidgetOptions
{
    public const TYPES = ['int', 'bool', 'string', 'select', 'menu'];

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * The defaults of every option.
     *
     * @return array<string, mixed>
     */
    public function defaults(WidgetInterface $widget): array
    {
        $values = [];

        foreach ($widget->settings() as $key => $definition) {
            $values[$key] = $this->normalise($definition, $definition['default'] ?? null) ?? $this->fallback($definition);
        }

        return $values;
    }

    /**
     * Stored values merged over the defaults; invalid or unknown values are ignored.
     *
     * @param array<array-key, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function resolve(WidgetInterface $widget, array $stored): array
    {
        $values = $this->defaults($widget);

        foreach ($widget->settings() as $key => $definition) {
            if (array_key_exists($key, $stored)) {
                $value = $this->normalise($definition, $stored[$key]);

                if ($value !== null) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * Validates posted values (from the group layout form).
     *
     * @param array<array-key, mixed> $posted
     *
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(WidgetInterface $widget, array $posted): array
    {
        $values = [];
        $errors = [];

        foreach ($widget->settings() as $key => $definition) {
            $type = (string) ($definition['type'] ?? 'string');
            $raw  = $posted[$key] ?? ($type === 'bool' ? '0' : ($type === 'menu' ? [] : null));
            $value = $this->normalise($definition, $raw);

            if ($type === 'menu' && is_array($value) && array_diff($value, array_keys($this->choices($definition))) !== []) {
                $value = null; // only entries of the back-office menu
            }

            if ($value === null) {
                $errors[$key] = lang('Core.widgets.invalidOption', [lang((string) ($definition['label'] ?? $key))]);

                continue;
            }

            $values[$key] = $value;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Choices of a `select` or `menu` option: value => translated label.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, string>
     */
    public function choices(array $definition): array
    {
        $type = $definition['type'] ?? 'string';

        if ($type === 'select') {
            $choices = [];

            foreach ((array) ($definition['options'] ?? []) as $value => $label) {
                $choices[(string) $value] = lang((string) $label);
            }

            return $choices;
        }

        if ($type === 'menu') {
            $choices = [];

            foreach ($this->registry->menuEntries() as $key => $declaration) {
                $label = $declaration['definition']['label'] ?? null;

                if (is_string($label) && isset($declaration['definition']['route'])) {
                    $choices[(string) $key] = lang($label) . ' (' . $declaration['module'] . ')';
                }
            }

            asort($choices, SORT_NATURAL | SORT_FLAG_CASE);

            return $choices;
        }

        return [];
    }

    /**
     * A value converted to the option's type, or null when it is not valid.
     *
     * @param array<string, mixed> $definition
     */
    private function normalise(array $definition, mixed $value): mixed
    {
        switch ($definition['type'] ?? 'string') {
            case 'int':
                if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,9}$/', trim($value)) === 1)) {
                    $value = (int) $value;
                    $min   = $definition['min'] ?? null;
                    $max   = $definition['max'] ?? null;

                    return (is_int($min) && $value < $min) || (is_int($max) && $value > $max) ? null : $value;
                }

                return null;

            case 'bool':
                return match (true) {
                    is_bool($value)                                  => $value,
                    in_array($value, [1, '1', 'on', 'true'], true)   => true,
                    in_array($value, [0, '0', '', 'false'], true)    => false,
                    default                                          => null,
                };

            case 'select':
                $value   = is_int($value) ? (string) $value : $value;
                $options = array_map('strval', array_keys((array) ($definition['options'] ?? [])));

                return is_string($value) && in_array($value, $options, true) ? $value : null;

            case 'menu':
                if (! is_array($value)) {
                    return null;
                }

                $keys = [];

                foreach ($value as $key) {
                    if (is_string($key) && preg_match('/^[A-Za-z0-9_.:-]{1,191}$/', $key) === 1 && ! in_array($key, $keys, true)) {
                        $keys[] = $key;
                    }
                }

                return count($keys) === count($value) ? $keys : null;

            default: // string
                if (! is_string($value) && ! is_int($value)) {
                    return null;
                }

                $value = trim((string) $value);

                return mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 ? null : $value;
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function fallback(array $definition): mixed
    {
        return match ($definition['type'] ?? 'string') {
            'int'    => (int) ($definition['min'] ?? 0),
            'bool'   => false,
            'menu'   => [],
            'select' => (string) (array_key_first((array) ($definition['options'] ?? [])) ?? ''),
            default  => '',
        };
    }
}
