<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use HTMLPurifier_Config;
use HTMLPurifier_Context;
use HTMLPurifier_URI;
use HTMLPurifier_URIFilter;

/**
 * HTMLPurifier URI filter of rich text: images (embedded URIs: `img src`) only from the site
 * itself (D-17-08): a relative URL (`/uploads/…`, `uploads/…`) or an absolute `http`/`https` URL
 * whose host is the base URL's host or one of `app.allowedHostnames`. An external image is
 * removed (its `img` loses the required `src`). Links are not affected.
 */
final class SiteImagesOnly extends HTMLPurifier_URIFilter
{
    /** @var string */
    public $name = 'SiteImagesOnly';

    /**
     * @param HTMLPurifier_URI     $uri
     * @param HTMLPurifier_Config  $config
     * @param HTMLPurifier_Context $context
     */
    public function filter(&$uri, $config, $context): bool
    {
        if ($context->get('EmbeddedURI', true) !== true) {
            return true;
        }

        if ($uri->host === null || $uri->host === '') {
            // Relative to the site; no scheme-relative or other schemes without a host.
            return $uri->scheme === null;
        }

        return in_array($uri->scheme, ['http', 'https'], true) && in_array(strtolower($uri->host), self::hosts(), true);
    }

    /**
     * @return list<string>
     */
    public static function hosts(): array
    {
        $app   = config('App');
        $hosts = [];
        $base  = parse_url((string) $app->baseURL, PHP_URL_HOST);

        if (is_string($base) && $base !== '') {
            $hosts[] = strtolower($base);
        }

        foreach ($app->allowedHostnames as $host) {
            if ($host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }
}
