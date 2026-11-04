<?php

declare(strict_types=1);

namespace Themes\{{Studly}};

use Modules\Core\Theme\ThemeManifest;

/**
 * Theme "{{Name}}"{{ParentDoc}}.
 *
 * Override a module view in Views/overrides/<Module>/<view>.php, a standard view or component in
 * Views/<path>.php (e.g. Views/components/badge.php) and colours in Assets/css/theme.css or by
 * redeclaring an option default below. See docs/themes.md.
 */
final class Theme extends ThemeManifest
{
    public string $name    = '{{Name}}';
    public string $version = '1.0.0';
    public string $author  = '';
    public ?string $parent = {{Parent}};

    /** Layouts this theme provides itself (the others come from the parent). */
    public array $layouts = {{Layouts}};

    /** Loaded after the parent's stylesheets. */
    public array $styles = ['css/theme.css'];

    /**
     * Options: redeclare a parent option to change only its default, e.g.
     * 'primaryColor' => ['default' => '#6f42c1'].
     */
    public array $options = [];
}
