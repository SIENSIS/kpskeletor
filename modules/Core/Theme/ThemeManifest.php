<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

/**
 * Base class of every theme manifest (`themes/<slug>/Theme.php`, class `Themes\<Studly>\Theme`).
 *
 * A theme is a folder with views (`Views/layouts`, `Views/partials`, `Views/components`,
 * `Views/overrides/<Module>/…`), assets (`Assets/css`, `Assets/js`, `Assets/img`) and language
 * files. A child theme names its `$parent` and only declares what it changes: layouts, regions,
 * menu locations and options are inherited and merged (see ThemeRegistry::chain()).
 * See docs/specification.md, "Themes", and docs/themes.md.
 */
abstract class ThemeManifest
{
    /** The layouts a theme may provide. */
    public const LAYOUTS = ['backend', 'public', 'auth', 'blank', 'print'];

    /** Human-readable name. */
    public string $name = '';

    /** Semantic version of the theme (e.g. "1.0.0"). */
    public string $version = '0.0.0';

    public string $author = '';

    /** Slug of the parent theme (e.g. "default"), or null for a standalone theme. */
    public ?string $parent = null;

    /**
     * Layouts provided by this theme (subset of LAYOUTS). A child theme inherits the others.
     *
     * @var list<string>
     */
    public array $layouts = [];

    /**
     * Regions per layout, filled by modules (widgets, menus, blocks): layout => list of regions.
     *
     * @var array<string, list<string>>
     */
    public array $regions = [];

    /**
     * Menu locations the layouts print (location => language key of its label).
     *
     * @var array<string, string>
     */
    public array $menuLocations = [];

    /**
     * Stylesheets loaded by the layouts, after the parent's: paths relative to `Assets/`, or
     * `vendor:<path>` for files under `public/assets/vendor/`.
     *
     * @var list<string>
     */
    public array $styles = [];

    /**
     * Scripts loaded by the layouts (`defer`), after the parent's; same path rules as $styles.
     *
     * @var list<string>
     */
    public array $scripts = [];

    /**
     * Options edited from the Settings UI, declared like settings: key => definition (type,
     * default, label, help, options, rules) plus `css` (the CSS custom property a `color` option
     * sets, e.g. "--bs-primary"). A child theme may redeclare a key to change only its default.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $options = [];
}
