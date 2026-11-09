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
 * (`options`: value => language key), `menu` (a list of back-office menu entry keys, e.g.
 * `Users.users` or `settings`; shown to each user only when they can open the entry) and `links`
 * (an ordered list of at most `maxLinks()` items: setting `Core.widgetMaxLinks`, default 20, each a menu entry `{"menu": key}` or a free link
 * `{"label", "url"}` whose address is an `https://` URL or an internal path starting with `/`).
 */
final class WidgetOptions
{
    public const TYPES = ['int', 'bool', 'string', 'select', 'menu', 'links'];

    /** Default and upper bound of the most items of a `links` option (setting `Core.widgetMaxLinks`). */
    public const MAX_LINKS = 20;

    public const MAX_LINKS_LIMIT = 100;

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
            $raw  = $posted[$key] ?? ($type === 'bool' ? '0' : (in_array($type, ['menu', 'links'], true) ? [] : null));

            if ($type === 'links') {
                $raw = self::fromRows($raw);
            }

            $value = $raw === null ? null : $this->normalise($definition, $raw);

            if ($type === 'menu' && is_array($value) && array_diff($value, array_keys($this->choices($definition))) !== []) {
                $value = null; // only entries of the back-office menu
            }

            if ($type === 'links' && is_array($value)) {
                $menu = array_filter(array_map(static fn (array $item): ?string => $item['menu'] ?? null, $value));

                if (array_diff($menu, array_keys($this->choices($definition))) !== []) {
                    $value = null;
                }
            }

            if ($type === 'links' && $value === null) {
                $errors[$key] = lang('Core.widgets.invalidLinks', [lang((string) ($definition['label'] ?? $key)), self::maxLinks($definition)]);

                continue;
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

        if ($type === 'menu' || $type === 'links') {
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

            case 'links':
                return self::links($value, self::maxLinks($definition));

            default: // string
                if (! is_string($value) && ! is_int($value)) {
                    return null;
                }

                $value = trim((string) $value);

                return mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 ? null : $value;
        }
    }

    /**
     * A `links` value (menu keys as plain strings are accepted, as stored by the `menu` type), or
     * null when an item is invalid or there are too many.
     *
     * @return list<array{menu: string}|array{label: string, url: string}>|null
     */
    private static function links(mixed $value, int $max): ?array
    {
        if (! is_array($value) || count($value) > $max) {
            return null;
        }

        $items = [];

        foreach ($value as $item) {
            $item = is_string($item) ? ['menu' => $item] : $item;

            if (is_array($item) && is_string($item['menu'] ?? null) && preg_match('/^[A-Za-z0-9_.:-]{1,191}$/', $item['menu']) === 1) {
                $items[] = ['menu' => $item['menu']];

                continue;
            }

            $label = is_array($item) && is_string($item['label'] ?? null) ? trim($item['label']) : '';
            $url   = is_array($item) && is_string($item['url'] ?? null) ? self::linkUrl($item['url']) : null;

            if ($label === '' || mb_strlen($label) > 100 || preg_match('/[\x00-\x1F\x7F]/', $label) === 1 || $url === null) {
                return null;
            }

            $items[] = ['label' => $label, 'url' => $url];
        }

        return $items;
    }

    /**
     * The most items of a `links` option: the definition's `max` when given, otherwise the setting
     * `Core.widgetMaxLinks` (default 20); always between 1 and 100.
     *
     * @param array<string, mixed> $definition
     */
    public static function maxLinks(array $definition = []): int
    {
        $max = $definition['max'] ?? null;

        if (! is_int($max)) {
            $setting = function_exists('setting') ? setting('Core.widgetMaxLinks') : null;
            $max     = is_numeric($setting) ? (int) $setting : self::MAX_LINKS;
        }

        return max(1, min(self::MAX_LINKS_LIMIT, $max));
    }

    /**
     * A free link's address: an `https://` URL without credentials or an internal path starting
     * with one `/`; null for anything else (`http:`, `javascript:`, `data:`, `//host`…).
     */
    public static function linkUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 500 || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') || parse_url($url) === false ? null : $url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    /**
     * The rows of the group layout form (`ref` = "menu:<key>", "url" or "" for an empty row,
     * `label`, `url`, `position`) as a `links` value in the order of their positions; null when
     * the rows are malformed.
     *
     * @return list<array<string, string>>|null
     */
    private static function fromRows(mixed $rows): ?array
    {
        if (! is_array($rows)) {
            return null;
        }

        $items = [];
        $index = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                return null;
            }

            $ref      = is_string($row['ref'] ?? null) ? $row['ref'] : '';
            $position = is_string($row['position'] ?? null) && preg_match('/^\d{1,4}$/', $row['position']) === 1 ? (int) $row['position'] : 10000 + $index;
            $index++;

            if ($ref === '') {
                continue;
            }

            if (str_starts_with($ref, 'menu:')) {
                $item = ['menu' => substr($ref, 5)];
            } elseif ($ref === 'url') {
                $item = ['label' => is_string($row['label'] ?? null) ? $row['label'] : '', 'url' => is_string($row['url'] ?? null) ? $row['url'] : ''];
            } else {
                return null;
            }

            $items[] = ['position' => $position, 'index' => $index, 'item' => $item];
        }

        usort($items, static fn (array $a, array $b): int => [$a['position'], $a['index']] <=> [$b['position'], $b['index']]);

        return array_map(static fn (array $entry): array => $entry['item'], $items);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function fallback(array $definition): mixed
    {
        return match ($definition['type'] ?? 'string') {
            'int'    => (int) ($definition['min'] ?? 0),
            'bool'   => false,
            'menu', 'links' => [],
            'select' => (string) (array_key_first((array) ($definition['options'] ?? [])) ?? ''),
            default  => '',
        };
    }
}
