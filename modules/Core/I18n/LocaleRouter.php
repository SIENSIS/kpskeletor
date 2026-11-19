<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use CodeIgniter\HTTP\Exceptions\RedirectException;
use CodeIgniter\Router\Router;

/**
 * CodeIgniter's router, reading the language prefix of public URLs first (D-37-02, docs/i18n.md
 * "Language in the URL"): `es/news/x` is routed as `news/x` with Spanish fixed for the request;
 * `ca/…` (the default language), a language the site does not offer or a prefix in front of a
 * non-public path (`es/backend`) redirect to the path without it. Module routes never mention
 * the language.
 */
class LocaleRouter extends Router
{
    public function handle(?string $uri = null)
    {
        $resolved = service('localeurls')->resolve((string) $uri);

        if ($resolved['redirect'] !== null) {
            $query = (string) service('request')->getUri()->getQuery();

            // No prefix is fixed now, so site_url() gives the path without one.
            $target = site_url($resolved['redirect'] === '' ? '/' : $resolved['redirect']) . ($query !== '' ? '?' . $query : '');

            throw new RedirectException(service('response')->redirect($target, 'auto', 301));
        }

        return parent::handle($resolved['route']);
    }
}
