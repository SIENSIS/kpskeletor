<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use Closure;
use Modules\Core\Settings\SettingCaster;
use Throwable;

/**
 * The options of the active theme (logo, colours, footer text…), declared like settings in the
 * theme manifests and inherited along the parent chain: a child theme may redeclare an option to
 * change only its default.
 *
 * Saved values are stored by codeigniter4/settings as `Theme.<key>` with the context
 * `theme:<slug>`; until a value is saved, the merged manifest default applies. Colour options with
 * a `css` key become CSS custom properties printed by the layouts (CSP-safe: validated hex
 * colours only, D-00-25).
 */
final class ThemeOptions
{
    private const HEX = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    /** @var Closure(string, string): mixed */
    private readonly Closure $store;

    /**
     * @param string                       $slug      the active theme
     * @param list<ThemeManifest>          $manifests the theme chain, active theme first
     * @param (Closure(string, string): mixed)|null $store reads a saved value (key, context); null when none
     */
    public function __construct(
        private readonly string $slug,
        private readonly array $manifests,
        ?Closure $store = null,
    ) {
        $this->store = $store ?? static function (string $key, string $context): mixed {
            try {
                return service('settings')->get('Theme.' . $key, $context);
            } catch (Throwable) {
                return null; // settings table not migrated yet (installer, first sync)
            }
        };
    }

    public static function context(string $slug): string
    {
        return 'theme:' . $slug;
    }

    /**
     * Option definitions merged from the root theme down to the active theme.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        foreach (array_reverse($this->manifests) as $manifest) {
            foreach ($manifest->options as $key => $definition) {
                $definitions[$key] = array_merge($definitions[$key] ?? [], $definition);
            }
        }

        return $this->definitions = $definitions;
    }

    public function get(string $key): mixed
    {
        $definition = $this->definitions()[$key] ?? null;

        if ($definition === null) {
            return null;
        }

        $type    = is_string($definition['type'] ?? null) ? $definition['type'] : 'string';
        $default = SettingCaster::cast($type, $definition['default'] ?? null);
        $stored  = ($this->store)($key, self::context($this->slug));

        if ($stored === null) {
            return $default;
        }

        $value = SettingCaster::cast($type, $stored);

        if ($type === 'color' && (! is_string($value) || preg_match(self::HEX, $value) !== 1)) {
            return $default;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];

        foreach (array_keys($this->definitions()) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * CSS custom properties set by colour options: `--bs-primary` and `--bs-primary-rgb`.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $variables = [];

        foreach ($this->definitions() as $key => $definition) {
            $property = $definition['css'] ?? null;

            if (! is_string($property) || preg_match('/^--[a-z0-9-]+$/', $property) !== 1 || ($definition['type'] ?? null) !== 'color') {
                continue;
            }

            $value = $this->get($key);

            if (! is_string($value) || preg_match(self::HEX, $value) !== 1) {
                continue;
            }

            $variables[$property]          = strtolower($value);
            $variables[$property . '-rgb'] = implode(', ', self::rgb($value));
        }

        return $variables;
    }

    /**
     * The `:root { … }` rule printed by the layouts (empty when no option sets a variable).
     */
    public function css(): string
    {
        $variables = $this->cssVariables();

        if ($variables === []) {
            return '';
        }

        $lines = [];

        foreach ($variables as $property => $value) {
            $lines[] = $property . ': ' . $value . ';';
        }

        return ':root { ' . implode(' ', $lines) . ' }';
    }

    /**
     * @return array{int, int, int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
