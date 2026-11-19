<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * The language of public URLs (docs/i18n.md, "Language in the URL"; D-37-02): a public path
 * without a prefix is in the site's default language (`/qui-som`), the other available languages
 * carry their code as the first segment (`/es/quienes-somos`). Modules\Core\I18n\LocaleUrls.
 */
class LocaleUrls extends BaseConfig
{
    /**
     * Off: public pages negotiate their language like the rest (profile, browser, default) and
     * the public language switch is hidden.
     */
    public bool $enabled = true;

    /**
     * First path segments that are never public pages and never carry a language prefix (the back
     * office, the API, sign-in, files, feeds, installer, assets…). `/es/<one of these>/…`
     * redirects to the path without the prefix.
     *
     * @var list<string>
     */
    public array $excluded = [
        'backend', 'api', 'auth', 'login', 'logout', 'register', 'reset-password',
        'files', 'install', 'progress', 'ical', 'theme-preview',
        'sitemap.xml', 'robots.txt', 'manifest.webmanifest', 'sw.js', 'pwa', 'favicon.ico',
        'assets', 'uploads', 'themes', 'vendor', 'writable', 'index.php',
    ];
}
