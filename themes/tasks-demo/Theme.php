<?php

declare(strict_types=1);

namespace Themes\TasksDemo;

use Modules\Core\Theme\ThemeManifest;

/**
 * Theme "Tasks Demo", child of "default": the finished theme of tutorial 9 (docs/tutorials/),
 * kept in the repository as tutorial material (D-00-32). It changes the primary colour and the
 * logo through the parent's options, adds its own CSS (`Assets/css/theme.css`) and overrides one
 * view of the Tasks module, the public page (`Views/overrides/Tasks/public/index.php`).
 * Select it in Settings › Appearance or with `theme.public = tasks-demo` in `.env`.
 *
 * Override a module view in Views/overrides/<Module>/<view>.php, a standard view or component in
 * Views/<path>.php (e.g. Views/components/badge.php) and colours in Assets/css/theme.css or by
 * redeclaring an option default below. See docs/themes.md.
 */
final class Theme extends ThemeManifest
{
    public string $name    = 'Tasks Demo';
    public string $version = '1.0.0';
    public string $author  = 'kpskeletor';
    public ?string $parent = 'default';

    /** Layouts this theme provides itself (the others come from the parent). */
    public array $layouts = [];

    /** Loaded after the parent's stylesheets. */
    public array $styles = ['css/theme.css'];

    /**
     * Options: redeclare a parent option to change only its default, e.g.
     * 'primaryColor' => ['default' => '#6f42c1'].
     */
    public array $options = [
        'primaryColor' => ['default' => '#6f42c1'],
        'logo'         => ['default' => 'themes/tasks-demo/img/logo.svg'],
    ];
}
