<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use Closure;
use Config\Services;
use Config\Theme as ThemeConfig;
use InvalidArgumentException;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Frontend\ThemeMode;
use Throwable;

/**
 * The themes of the current request (service `theme`).
 *
 * The back office and the public site may use different themes (`Theme.backend`,
 * `Theme.public`); BackendController switches the area to `backend`, everything else renders in
 * the `public` area. Views are resolved along the active theme's parent chain (ViewResolver), so
 * modules never hard-code a theme path: they call `theme_view()` and the layouts call
 * `theme_layout()`, `theme_component()` and `theme_region()`.
 */
final class ThemeManager
{
    public const AREAS = ['backend', 'public'];

    private string $area = 'public';

    /** @var array<string, list<string>> area => chain */
    private array $chains = [];

    /** @var array<string, ViewResolver> */
    private array $resolvers = [];

    /** @var array<string, ThemeOptions> */
    private array $options = [];

    /** @var array<string, ThemeMode> */
    private array $modes = [];

    private readonly Regions $regions;

    /** @var Closure(string): mixed */
    private readonly Closure $setting;

    /**
     * @param (Closure(string): mixed)|null $setting reads a setting ("Theme.backend"); defaults to setting()
     */
    public function __construct(
        private readonly ThemeRegistry $registry = new ThemeRegistry(),
        ?Regions $regions = null,
        ?Closure $setting = null,
        private readonly ?ThemeConfig $config = null,
    ) {
        $this->regions = $regions ?? new Regions();
        $this->setting = $setting ?? static function (string $key): mixed {
            try {
                return setting($key);
            } catch (Throwable) {
                return null; // settings table not migrated yet
            }
        };
    }

    public function registry(): ThemeRegistry
    {
        return $this->registry;
    }

    public function setArea(string $area): void
    {
        if (! in_array($area, self::AREAS, true)) {
            throw new InvalidArgumentException(lang('Core.theme.area', [$area]));
        }

        $this->area = $area;
    }

    public function area(): string
    {
        return $this->area;
    }

    /**
     * Uses a theme for an area during this request (theme preview, tests), ignoring the setting.
     *
     * @throws ThemeNotFoundException
     */
    public function force(string $area, string $slug): void
    {
        if (! in_array($area, self::AREAS, true)) {
            throw new InvalidArgumentException(lang('Core.theme.area', [$area]));
        }

        $chain = $this->registry->chain($slug);
        unset($this->resolvers[$area], $this->options[$area], $this->modes[$area]);
        $this->chains[$area] = $chain;
    }

    /**
     * Slug of the active theme of an area (the current one by default).
     */
    public function slug(?string $area = null): string
    {
        return $this->chain($area)[0];
    }

    /**
     * The active theme and its ancestors, active theme first. A missing or broken theme falls
     * back to `Theme.fallback` (logged), so a bad setting never takes the site down.
     *
     * @return list<string>
     */
    public function chain(?string $area = null): array
    {
        $area ??= $this->area;

        if (isset($this->chains[$area])) {
            return $this->chains[$area];
        }

        $config   = $this->config ?? config(ThemeConfig::class);
        $fallback = $config->fallback;
        $selected = ($this->setting)('Theme.' . $area);
        $selected = is_string($selected) && $selected !== '' ? $selected : ($config->{$area} ?? $fallback);

        try {
            return $this->chains[$area] = $this->registry->chain($selected);
        } catch (ThemeNotFoundException $e) {
            if ($selected === $fallback) {
                throw $e;
            }

            log_message('error', 'Theme "{theme}" cannot be used ({message}); falling back to "{fallback}".', [
                'theme'    => $selected,
                'message'  => $e->getMessage(),
                'fallback' => $fallback,
            ]);

            return $this->chains[$area] = $this->registry->chain($fallback);
        }
    }

    public function manifest(?string $area = null): ThemeManifest
    {
        return $this->registry->get($this->slug($area));
    }

    /**
     * @return list<ThemeManifest> active theme first
     */
    public function manifests(?string $area = null): array
    {
        return array_map($this->registry->get(...), $this->chain($area));
    }

    public function resolver(?string $area = null): ViewResolver
    {
        $area ??= $this->area;

        if (isset($this->resolvers[$area])) {
            return $this->resolvers[$area];
        }

        $themes = [];

        foreach ($this->chain($area) as $slug) {
            $themes[] = ['namespace' => ThemeRegistry::namespaceOf($slug), 'path' => $this->registry->path($slug)];
        }

        return $this->resolvers[$area] = new ViewResolver($themes);
    }

    public function resolve(string $view, ?string $area = null): string
    {
        // Loading the manifests (re)registers the themes' namespaces, which the view renderer
        // needs even if the autoloader service was replaced since the chain was cached.
        foreach ($this->chain($area) as $slug) {
            $this->registry->get($slug);
        }

        return $this->resolver($area)->resolve($view);
    }

    /**
     * The view name of a layout (`backend`, `public`, `auth`, `blank`, `print`).
     */
    public function layout(string $name, ?string $area = null): string
    {
        if (! in_array($name, ThemeManifest::LAYOUTS, true)) {
            throw new InvalidArgumentException(lang('Core.theme.layout', [$name]));
        }

        return $this->resolve('layouts/' . $name, $area);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options view() options; `isolated` => true renders with a new
     *                                      renderer that sees only `$data` (components)
     */
    public function render(string $view, array $data = [], array $options = [], ?string $area = null): string
    {
        $name = $this->resolve($view, $area);

        if (($options['isolated'] ?? false) === true) {
            unset($options['isolated'], $options['saveData']);

            // The shared renderer keeps the data of every view rendered with saveData (CodeIgniter's
            // default), and `saveData => false` still reads it: a component would see variables it
            // was not given (e.g. a `$message` of an earlier view). A new renderer sees only $data.
            return Services::renderer(null, null, false)->setData($data, 'raw')->render($name, $options, false);
        }

        return view($name, $data, $options);
    }

    public function options(?string $area = null): ThemeOptions
    {
        $area ??= $this->area;

        return $this->options[$area] ??= new ThemeOptions($this->slug($area), $this->manifests($area));
    }

    /**
     * The light/dark/auto mode of the area's pages (theme options, user choice, cookie).
     */
    public function mode(?string $area = null): ThemeMode
    {
        $area ??= $this->area;

        return $this->modes[$area] ??= new ThemeMode($this->options($area));
    }

    public function regions(): Regions
    {
        return $this->regions;
    }

    /**
     * Whether the active theme (or an ancestor) declares a region for a layout.
     */
    public function hasRegion(string $layout, string $region, ?string $area = null): bool
    {
        foreach ($this->manifests($area) as $manifest) {
            if (in_array($region, $manifest->regions[$layout] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Content of a region, empty when the theme does not declare it for the layout.
     */
    public function region(string $layout, string $region, ?string $area = null): string
    {
        return $this->hasRegion($layout, $region, $area) ? $this->regions->render($region) : '';
    }

    /**
     * Menu locations of the active theme and its ancestors (location => label key).
     *
     * @return array<string, string>
     */
    public function menuLocations(?string $area = null): array
    {
        $locations = [];

        foreach (array_reverse($this->manifests($area)) as $manifest) {
            $locations = array_merge($locations, $manifest->menuLocations);
        }

        return $locations;
    }

    /**
     * URLs of the stylesheets to load: the root theme's first, the active theme's last.
     *
     * @return list<string>
     */
    public function styles(?string $area = null): array
    {
        return $this->assetUrls('styles', $area);
    }

    /**
     * @return list<string>
     */
    public function scripts(?string $area = null): array
    {
        return $this->assetUrls('scripts', $area);
    }

    /**
     * URL of a theme asset (path relative to `Assets/`, or `vendor:<path>` under
     * `public/assets/vendor/`), found along the chain from the active theme; null when no theme
     * of the chain ships it. Theme assets are published to `public/themes/<slug>/` by
     * `assets:publish` and versioned with the asset manifest's hash.
     */
    public function assetUrl(string $path, ?string $area = null): ?string
    {
        return $this->findAsset($path, $this->chain($area));
    }

    /**
     * Forgets the resolved themes (after the settings change, and in tests).
     */
    public function reset(): void
    {
        $this->chains    = [];
        $this->resolvers = [];
        $this->options   = [];
        $this->modes     = [];
        $this->regions->clear();
    }

    /**
     * @param 'scripts'|'styles' $kind
     *
     * @return list<string>
     */
    private function assetUrls(string $kind, ?string $area): array
    {
        $chain = $this->chain($area);
        $urls  = [];

        // Root theme first; a declared file is looked up from the theme that declares it, so a
        // child's css/theme.css never hides its parent's css/theme.css.
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            foreach ($this->registry->get($chain[$i])->{$kind} as $path) {
                $url = $this->findAsset($path, array_slice($chain, $i));

                if ($url !== null && ! in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * @param list<string> $chain themes to look in, in order
     */
    private function findAsset(string $path, array $chain): ?string
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'vendor:')) {
            $relative = substr($path, 7);

            return service('assets')->vendorUrl($relative);
        }

        foreach ($chain as $slug) {
            $source = $this->registry->path($slug) . '/Assets/' . $path;

            if (is_file($source)) {
                // Hash from the asset manifest (assets:publish); before the first publish, the source's.
                return service('assets')->url('theme:' . $slug . '::' . $path)
                    ?? base_url('themes/' . $slug . '/' . $path) . '?v=' . AssetPublisher::hash($source);
            }
        }

        return null;
    }
}
