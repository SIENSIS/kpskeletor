<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Theme settings: `setting('Theme.backend')` and `setting('Theme.public')` (saved from the
 * Settings UI; these properties are the defaults). Theme options are stored as `Theme.<option>`
 * with the context `theme:<slug>` (see Modules\Core\Theme\ThemeOptions).
 *
 * Override in app/Config/Theme.php or .env (`theme.backend = corporate`).
 */
class Theme extends BaseConfig
{
    /** Theme of the back office (slug of a folder in themes/). */
    public string $backend = 'default';

    /** Theme of the public site and the authentication pages. */
    public string $public = 'default';

    /** Theme used when the selected one is missing or broken. */
    public string $fallback = 'default';

    /**
     * Theme preview pages (`/theme-preview/<layout>?theme=<slug>`): every layout and standard
     * component with sample content, for theme authors. Only in development, and only when
     * enabled (`.env`: `theme.preview = true`); otherwise the pages answer 404.
     */
    public bool $preview = false;
}
