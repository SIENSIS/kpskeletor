<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\SemVer;

/**
 * Checks a theme manifest and its files (run by `modules:sync` and `make:theme`); every message
 * names the theme and the key, e.g. "corporate: $layouts must be a subset of: backend, public…".
 */
final class ThemeValidator
{
    /** @var list<string> */
    private array $errors = [];

    private string $slug = '';

    public function __construct(private readonly ThemeRegistry $registry = new ThemeRegistry())
    {
    }

    /**
     * Validates every theme in the themes folder.
     *
     * @return list<string> errors (empty when every theme is valid)
     */
    public function validateAll(): array
    {
        $errors = [];

        foreach ($this->registry->slugs() as $slug) {
            $errors = [...$errors, ...$this->validate($slug)];
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function validate(string $slug): array
    {
        $this->errors = [];
        $this->slug   = $slug;

        try {
            $manifest = $this->registry->get($slug);
            $chain    = $this->registry->chain($slug);
        } catch (ThemeNotFoundException $e) {
            return [$slug . ': ' . $e->getMessage()];
        }

        if (trim($manifest->name) === '') {
            $this->error('$name', 'required');
        }

        if (! SemVer::isVersion($manifest->version)) {
            $this->error('$version', 'version');
        }

        $this->checkLayouts($manifest, $chain);
        $this->checkRegions($manifest);
        $this->checkMenuLocations($manifest);
        $this->checkAssets($manifest, $chain);
        $this->checkOptions($chain);

        return $this->errors;
    }

    /**
     * @param list<string> $chain
     */
    private function checkLayouts(ThemeManifest $manifest, array $chain): void
    {
        foreach ($manifest->layouts as $layout) {
            if (! in_array($layout, ThemeManifest::LAYOUTS, true)) {
                $this->error('$layouts', 'oneOf', [implode(', ', ThemeManifest::LAYOUTS)]);

                continue;
            }

            if (! is_file($this->registry->path($this->slug) . '/Views/layouts/' . $layout . '.php')) {
                $this->error('$layouts', 'layoutFile', ["Views/layouts/{$layout}.php"]);
            }
        }

        // Every layout must be provided by the theme or an ancestor.
        foreach (ThemeManifest::LAYOUTS as $layout) {
            $found = false;

            foreach ($chain as $slug) {
                if (in_array($layout, $this->registry->get($slug)->layouts, true)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->error('$layouts', 'layoutMissing', [$layout]);
            }
        }
    }

    private function checkRegions(ThemeManifest $manifest): void
    {
        foreach ($manifest->regions as $layout => $regions) {
            if (! in_array($layout, ThemeManifest::LAYOUTS, true)) {
                $this->error("\$regions['{$layout}']", 'oneOf', [implode(', ', ThemeManifest::LAYOUTS)]);

                continue;
            }

            foreach (self::values($regions) as $region) {
                if (! is_string($region) || preg_match('/^[a-z][a-zA-Z0-9]*$/', $region) !== 1) {
                    $this->error("\$regions['{$layout}']", 'name', [is_scalar($region) ? (string) $region : '?']);
                }
            }
        }
    }

    private function checkMenuLocations(ThemeManifest $manifest): void
    {
        foreach (self::entries($manifest->menuLocations) as $location => $label) {
            if (preg_match('/^[a-z][a-z0-9_-]*$/', (string) $location) !== 1 || ! is_string($label) || $label === '') {
                $this->error("\$menuLocations['{$location}']", 'name', [(string) $location]);
            }
        }
    }

    /**
     * @param list<string> $chain
     */
    private function checkAssets(ThemeManifest $manifest, array $chain): void
    {
        foreach (['styles', 'scripts'] as $kind) {
            foreach (self::values($manifest->{$kind}) as $path) {
                if (! is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#^[a-z]+://#i', $path) === 1) {
                    $this->error("\${$kind}", 'asset', [is_string($path) ? $path : '?']);

                    continue;
                }

                if (str_starts_with($path, 'vendor:')) {
                    $exists = is_file(FCPATH . 'assets/vendor/' . substr($path, 7));
                } else {
                    $exists = false;

                    foreach ($chain as $slug) {
                        if (is_file($this->registry->path($slug) . '/Assets/' . $path)) {
                            $exists = true;
                            break;
                        }
                    }
                }

                if (! $exists) {
                    $this->error("\${$kind}", 'assetMissing', [$path]);
                }
            }
        }
    }

    /**
     * Options are checked merged along the chain (a child may redeclare only the default).
     *
     * @param list<string> $chain
     */
    private function checkOptions(array $chain): void
    {
        $options = new ThemeOptions($this->slug, array_map($this->registry->get(...), $chain), static fn (): mixed => null);

        foreach ($options->definitions() as $key => $definition) {
            $name = "\$options['{$key}']";

            if (preg_match('/^[a-z][a-zA-Z0-9]*$/', (string) $key) !== 1) {
                $this->error($name, 'name', [(string) $key]);
            }

            $type = $definition['type'] ?? null;

            if (! in_array($type, ManifestValidator::SETTING_TYPES, true)) {
                $this->error("{$name}['type']", 'oneOf', [implode(', ', ManifestValidator::SETTING_TYPES)]);
            }

            if (isset($definition['css'])) {
                if ($type !== 'color' || ! is_string($definition['css']) || preg_match('/^--[a-z0-9-]+$/', $definition['css']) !== 1) {
                    $this->error("{$name}['css']", 'css');
                }
            }

            if ($type === 'color' && is_string($definition['default'] ?? null) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $definition['default']) !== 1) {
                $this->error("{$name}['default']", 'color');
            }
        }
    }

    /**
     * The values of a manifest property, unchecked (manifests are code written by theme authors).
     *
     * @return list<mixed>
     */
    private static function values(mixed $list): array
    {
        return is_array($list) ? array_values($list) : [];
    }

    /**
     * @return array<mixed>
     */
    private static function entries(mixed $map): array
    {
        return is_array($map) ? $map : [];
    }

    /**
     * @param list<string> $args
     */
    private function error(string $key, string $rule, array $args = []): void
    {
        $this->errors[] = lang('Core.theme.error', [$this->slug, $key, lang('Core.theme.rule.' . $rule, $args)]);
    }
}
