<?php

declare(strict_types=1);

namespace Modules\Core\Routing;

use CodeIgniter\Events\Events;
use Config\App;
use Config\LocaleUrls;
use Modules\Core\I18n\Locales;
use Throwable;

/**
 * The back-office path prefix (D-40-01): `app.backendPath` in `.env` (`Config\App::$backendPath`,
 * default `backend`), read from configuration at boot, never from the database (routes are built
 * before any query).
 *
 * Code builds back-office paths and URLs with `backend_path()` / `backend_url()` (this class).
 *
 * **Stored links** (notification links, dashboard quick links, back-office menu links…) are
 * written relative to the back office with the canonical prefix `backend` (`backend_link()`,
 * `store()`), whatever the installed prefix is, and resolved when shown (`resolve()`): links
 * stored before 1.1 (all of them start with `backend/`) keep working, and a later change of the
 * prefix (`php spark app:backend-path`) does not break them. `backend` therefore stays reserved as
 * a top-level path on every site (D-40-02).
 */
final class BackendPath
{
    /** The default prefix, and the prefix of stored back-office links. */
    public const CANONICAL = 'backend';

    /** Lowercase letters, digits and hyphens, 2–30 characters, starting with a letter, one segment. */
    public const PATTERN = '/^[a-z][a-z0-9-]{0,28}[a-z0-9]$/';

    /**
     * Top-level paths that a back office can never use (besides the routes of the installed
     * modules, the language codes and `Config\LocaleUrls::$excluded`).
     */
    public const RESERVED = [
        'api', 'install', 'progress', 'login', 'logout', 'register', 'auth', 'uploads', 'assets',
        'themes', 'ical', 'files', 'reset-password', 'theme-preview', 'pwa', 'vendor', 'writable',
        'public', 'index', 'well-known', 'cgi-bin', 'language',
    ];

    /**
     * The installed prefix (a value that breaks the format falls back to `backend`).
     */
    public static function current(): string
    {
        try {
            $value = strtolower(trim(config(App::class)->backendPath, " /\t"));
        } catch (Throwable) {
            $value = self::CANONICAL;
        }

        return preg_match(self::PATTERN, $value) === 1 ? $value : self::CANONICAL;
    }

    /**
     * A back-office path: `path()` = `admin`, `path('news/posts')` = `admin/news/posts`.
     */
    public static function path(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return self::current();
        }

        return self::current() . (str_starts_with($path, '?') || str_starts_with($path, '#') ? '' : '/') . $path;
    }

    /**
     * The absolute URL of a back-office path.
     */
    public static function url(string $path = ''): string
    {
        return site_url(self::path($path));
    }

    /**
     * The stored form of a back-office link: `link('news/read/7')` = `backend/news/read/7`.
     */
    public static function link(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? self::CANONICAL : self::CANONICAL . (str_starts_with($path, '?') || str_starts_with($path, '#') ? '' : '/') . $path;
    }

    /**
     * Whether a path (no host, with or without the leading slash) is under the current prefix.
     */
    public static function contains(string $path): bool
    {
        return self::rest($path, self::current()) !== null;
    }

    /**
     * Turns a stored internal path into the current one: `backend/…` (the canonical prefix) is
     * moved under the installed prefix; anything else is returned as it is. The leading slash is
     * kept.
     */
    public static function resolve(string $path): string
    {
        $current = self::current();

        if ($current === self::CANONICAL) {
            return $path;
        }

        $rest = self::rest($path, self::CANONICAL);

        if ($rest === null) {
            return $path;
        }

        return (str_starts_with($path, '/') ? '/' : '') . $current . $rest;
    }

    /**
     * Turns an internal path under the installed prefix (or under `$prefix`) into its stored form
     * (canonical prefix); anything else is returned as it is. The leading slash is kept.
     */
    public static function store(string $path, ?string $prefix = null): string
    {
        $prefix ??= self::current();

        if ($prefix === self::CANONICAL) {
            return $path;
        }

        $rest = self::rest($path, $prefix);

        if ($rest === null) {
            return $path;
        }

        return (str_starts_with($path, '/') ? '/' : '') . self::CANONICAL . $rest;
    }

    /**
     * Why a path cannot be the back-office prefix, as a language line, or null when it can.
     *
     * @param list<string> $taken other top-level paths in use (installed modules' routes, pages…)
     */
    public static function error(string $candidate, array $taken = []): ?string
    {
        if (preg_match(self::PATTERN, $candidate) !== 1) {
            return lang('Core.backendPath.format');
        }

        if (in_array($candidate, self::reserved($taken), true)) {
            return lang('Core.backendPath.reserved', [$candidate]);
        }

        $errors = new BackendPathCheck($candidate);
        Events::trigger('backendPathCheck', $errors);

        return $errors->errors()[0] ?? null;
    }

    /**
     * Every reserved top-level path (the default prefix `backend` can always be chosen again).
     *
     * @param list<string> $taken
     *
     * @return list<string>
     */
    public static function reserved(array $taken = []): array
    {
        $reserved = [...self::RESERVED, ...Locales::supported(), ...$taken];

        try {
            $reserved = [...$reserved, ...(new LocaleUrls())->excluded];
        } catch (Throwable) {
            // configuration not loadable: the list above still applies
        }

        $reserved = array_map(static fn (string $path): string => strtolower(trim($path, '/')), $reserved);

        return array_values(array_unique(array_filter(
            $reserved,
            static fn (string $path): bool => $path !== '' && $path !== self::current() && $path !== self::CANONICAL,
        )));
    }

    /**
     * The first segments of the routes defined now, except the back office's own.
     *
     * @return list<string>
     */
    public static function routedSegments(): array
    {
        $current  = self::current();
        $segments = [];

        try {
            $routes = service('routes');
            $routes->loadRoutes();

            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'CLI'] as $verb) {
                foreach (array_keys($routes->getRoutes($verb)) as $from) {
                    $first = strtolower(explode('/', trim((string) $from, '/'), 2)[0]);

                    if ($first !== '' && $first !== $current && preg_match('/^[a-z0-9-]+$/', $first) === 1) {
                        $segments[$first] = true;
                    }
                }
            }
        } catch (Throwable) {
            return [];
        }

        return array_keys($segments);
    }

    /**
     * What follows `$prefix` in a path (`''`, `/…`, `?…` or `#…`), or null when the path is not
     * under it.
     */
    private static function rest(string $path, string $prefix): ?string
    {
        $trimmed = ltrim($path, '/');

        if ($trimmed === $prefix) {
            return '';
        }

        foreach (['/', '?', '#'] as $separator) {
            if (str_starts_with($trimmed, $prefix . $separator)) {
                return substr($trimmed, strlen($prefix));
            }
        }

        return null;
    }
}
