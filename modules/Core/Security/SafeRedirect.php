<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use Config\App;

/**
 * Redirect targets limited to this site (docs/specification.md, "Security baseline › Data
 * handling": redirects only to internal paths). Used by `safe_redirect()` for every `return`,
 * `redirect` or `next` value that comes from the request.
 *
 * Accepted: a path (`/backend/news?page=2`, `backend/news`) or an absolute http(s) URL of the
 * site's own host (`app.baseURL` or `app.allowedHostnames`). Refused: other hosts,
 * protocol-relative `//host`, backslashes, other schemes (`javascript:`, `data:`), user
 * information (`https://site@evil`) and control characters.
 */
final class SafeRedirect
{
    public function __construct(private readonly ?App $config = null)
    {
    }

    /**
     * The URL to redirect to: the target when it is internal, otherwise the fallback.
     */
    public function target(?string $url, string $fallback = '/'): string
    {
        if ($url !== null && $this->isInternal($url)) {
            return trim($url);
        }

        return $this->isInternal($fallback) ? $fallback : '/';
    }

    public function isInternal(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return false;
        }

        if (self::isInternalPath($url)) {
            return true;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return in_array(strtolower($parts['host']), $this->hosts(), true);
    }

    /**
     * A path of this site: `/x` (never `//x`) or a relative path without a scheme.
     */
    public static function isInternalPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '//') || preg_match('/[\x00-\x1F\x7F\\\\]/', $path) === 1) {
            return false;
        }

        if (str_starts_with($path, '/')) {
            return true;
        }

        // "backend/news" or "?page=2": no scheme (a colon before the first "/", "?" or "#").
        $head = strpbrk($path, '/?#');
        $head = $head === false ? $path : substr($path, 0, strlen($path) - strlen($head));

        return ! str_contains($head, ':');
    }

    /**
     * @return list<string>
     */
    private function hosts(): array
    {
        $config = $this->config ?? config(App::class);
        $hosts  = [strtolower((string) parse_url($config->baseURL, PHP_URL_HOST))];

        foreach ($config->allowedHostnames as $host) {
            $hosts[] = strtolower($host);
        }

        return array_values(array_filter($hosts, static fn (string $h): bool => $h !== ''));
    }
}
