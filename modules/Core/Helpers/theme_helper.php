<?php

declare(strict_types=1);

/**
 * Theme helpers: render views through the active theme (see Modules\Core\Theme\ThemeManager).
 * Loaded by app/Config/Autoload.php ($helpers).
 */

use CodeIgniter\HTTP\IncomingRequest;
use Modules\Core\Frontend\ThemeMode;
use Modules\Core\Theme\ThemeManager;

if (! function_exists('theme')) {
    function theme(): ThemeManager
    {
        return service('theme');
    }
}

if (! function_exists('theme_view')) {
    /**
     * Renders a view through the theme chain: `theme_view('Modules\News\Views\posts/index', $data)`
     * or a standard view (`theme_view('components/card', $data)`).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options view() options
     */
    function theme_view(string $view, array $data = [], array $options = []): string
    {
        return theme()->render($view, $data, $options);
    }
}

if (! function_exists('theme_layout')) {
    /**
     * The view name of a layout, for `$this->extend(theme_layout('backend'))`.
     */
    function theme_layout(string $name): string
    {
        return theme()->layout($name);
    }
}

if (! function_exists('theme_resolve')) {
    /**
     * The view name that renders a view, for `$this->include(theme_resolve('partials/footer'))`.
     */
    function theme_resolve(string $view): string
    {
        return theme()->resolve($view);
    }
}

if (! function_exists('theme_component')) {
    /**
     * Renders a standard component (`components/<name>`) with its own data only.
     *
     * @param array<string, mixed> $data
     */
    function theme_component(string $name, array $data = []): string
    {
        return theme()->render('components/' . $name, $data, ['saveData' => false]);
    }
}

if (! function_exists('theme_partial')) {
    /**
     * Renders a theme partial (`partials/<name>`) with its own data only.
     *
     * @param array<string, mixed> $data
     */
    function theme_partial(string $name, array $data = []): string
    {
        return theme()->render('partials/' . $name, $data, ['saveData' => false]);
    }
}

if (! function_exists('theme_region')) {
    /**
     * The content of a region of a layout (empty when the theme does not declare it).
     */
    function theme_region(string $layout, string $region): string
    {
        return theme()->region($layout, $region);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * URL of a theme asset (path relative to the theme's `Assets/`, or `vendor:<path>`), or ''.
     */
    function theme_asset(string $path): string
    {
        return theme()->assetUrl($path) ?? '';
    }
}

if (! function_exists('theme_option')) {
    /**
     * Value of an option of the active theme (saved value or manifest default).
     */
    function theme_option(string $key): mixed
    {
        return theme()->options()->get($key);
    }
}

if (! function_exists('theme_mode')) {
    /**
     * The colour mode of the current page (light, dark or auto): `theme_mode()->attributes()`
     * prints the `<html>` attributes, `theme_mode()->switchVisible()` tells whether to show the
     * switch.
     */
    function theme_mode(): ThemeMode
    {
        return theme()->mode();
    }
}

if (! function_exists('theme_sidebar_class')) {
    /**
     * Body class of the back-office sidebar state remembered by `core/ui/sidebar.js`
     * (`sidebar-collapsed`, `sidebar-expanded` or none: icon-only on tablets, full on desktops).
     */
    function theme_sidebar_class(): string
    {
        $request = service('request');
        $state   = $request instanceof IncomingRequest ? $request->getCookie('kp_sidebar') : null;

        return match ($state) {
            'collapsed' => 'sidebar-collapsed',
            'expanded'  => 'sidebar-expanded',
            default     => '',
        };
    }
}
