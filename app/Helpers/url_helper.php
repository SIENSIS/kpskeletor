<?php

declare(strict_types=1);

use CodeIgniter\HTTP\SiteURI;
use Config\App;

/*
 * The application's URL helper: loaded before CodeIgniter's (whose functions are only defined when
 * missing), it keeps the language prefix of the current public page in internal links
 * (`site_url('news')` on `/es/…` gives `/es/news`; D-37-02, docs/i18n.md "Language in the URL").
 * The other functions of CodeIgniter's url_helper are unchanged (it is still loaded after this file).
 */

if (! function_exists('site_url')) {
    /**
     * Returns a site URL as defined by the App config, with the current public page's language
     * prefix for public paths (Modules\Core\I18n\LocaleUrls::localize()).
     *
     * @param list<string>|string $relativePath URI string or array of URI segments.
     * @param string|null         $scheme       URI scheme. E.g., http, ftp. If empty string '' is set, a protocol-relative link is returned.
     * @param App|null            $config       Alternate configuration to use.
     */
    function site_url($relativePath = '', ?string $scheme = null, ?App $config = null): string
    {
        if (is_string($relativePath)) {
            $relativePath = service('localeurls')->localize($relativePath);
        }

        $currentURI = service('request')->getUri();

        assert($currentURI instanceof SiteURI);

        return $currentURI->siteUrl($relativePath, $scheme, $config);
    }
}
