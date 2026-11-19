<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use CodeIgniter\HTTP\SiteURI;
use Config\LocaleUrls as LocaleUrlsConfig;

/**
 * The language of public URLs (service `localeurls`, docs/i18n.md "Language in the URL",
 * D-37-02). A public path without a prefix is in the site's default language; the other available
 * languages carry their code as the first segment (`es/quienes-somos`). Paths listed in
 * `Config\LocaleUrls::$excluded` (back office, API, sign-in…) never carry one.
 *
 * `Modules\Core\I18n\LocaleRouter` reads the prefix of each request (`resolve()`), the `locale`
 * filter applies the language it fixed, and the application's `site_url()` (app/Helpers/url_helper.php)
 * keeps the current prefix in internal links (`localize()`). Modules may give the same page's path
 * in other languages (`setAlternates()`, e.g. Pages' slugs per language) for the language switch
 * and the `hreflang` links.
 */
final class LocaleUrls
{
    /** The language fixed by the URL of a public request, or null (not public, or disabled). */
    private ?string $locale = null;

    /** The public path without its prefix (`quienes-somos`, `` for the home page). */
    private string $path = '';

    /** @var array<string, string> locale => path of the current page in that language */
    private array $alternates = [];

    public function __construct(private readonly LocaleUrlsConfig $config = new LocaleUrlsConfig())
    {
    }

    public function enabled(): bool
    {
        return $this->config->enabled;
    }

    /**
     * Splits a path into its language prefix (a supported locale) and the rest.
     *
     * @return array{0: string|null, 1: string}
     */
    public function split(string $path): array
    {
        $path  = trim($path, '/');
        $parts = explode('/', $path, 2);

        if ($parts[0] !== '' && in_array($parts[0], Locales::supported(), true)) {
            return [$parts[0], $parts[1] ?? ''];
        }

        return [null, $path];
    }

    /**
     * Whether a path (without prefix) is a public page that carries the language in its URL.
     */
    public function isPublic(string $path): bool
    {
        $first = explode('/', trim($path, '/'), 2)[0];

        return ! in_array(strtolower($first), $this->config->excluded, true);
    }

    /**
     * Reads the URL of a request: the path to route and, when a redirect is needed (the default
     * language or a language the site does not offer written as a prefix, a prefix in front of a
     * non-public path), the path to redirect to. Fixes the request's language for public paths.
     *
     * @return array{route: string, redirect: string|null}
     */
    public function resolve(string $path): array
    {
        $this->locale     = null;
        $this->path       = '';
        $this->alternates = [];

        if (! $this->enabled()) {
            return ['route' => $path, 'redirect' => null];
        }

        [$prefix, $rest] = $this->split($path);

        if ($prefix !== null && (! $this->isPublic($rest) || $prefix === Locales::default() || ! Locales::isAvailable($prefix))) {
            return ['route' => $rest, 'redirect' => $rest];
        }

        if ($this->isPublic($rest)) {
            $this->locale = $prefix ?? Locales::default();
            $this->path   = $rest;
        }

        return ['route' => $prefix === null ? $path : $rest, 'redirect' => null];
    }

    /**
     * The language fixed by the URL of this public request, or null.
     */
    public function locale(): ?string
    {
        return $this->locale;
    }

    /**
     * The public path of this request without its prefix.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * The prefix of a language in public URLs: none for the default language.
     */
    public function prefix(string $locale): string
    {
        return $locale === Locales::default() ? '' : $locale . '/';
    }

    /**
     * A relative path with the current request's prefix when it is a public path (used by the
     * application's `site_url()`); absolute URLs, non-public paths and paths that already start
     * with a language are left alone.
     */
    public function localize(string $relativePath): string
    {
        if ($this->locale === null || $this->locale === Locales::default() || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $relativePath) === 1) {
            return $relativePath;
        }

        $path = ltrim($relativePath, '/');

        if (! $this->isPublic($path) || $this->split($path)[0] !== null) {
            return $relativePath;
        }

        return $this->prefix($this->locale) . $path;
    }

    /**
     * The absolute URL of a public path in a language (whatever the current one).
     */
    public function url(string $path, string $locale, string $query = ''): string
    {
        $uri      = service('request')->getUri();
        $relative = $this->prefix($locale) . ltrim($path, '/');
        $relative = $relative === '' ? '/' : $relative; // the same URL as site_url('/')
        $url      = $uri instanceof SiteURI
            ? $uri->siteUrl($relative)
            : rtrim((string) config('App')->baseURL, '/') . '/' . ltrim($relative, '/');

        return $url . ($query !== '' ? '?' . $query : '');
    }

    /**
     * The current page's path in other languages (locale => path without prefix), when it differs
     * from the current path (Pages: one slug per language).
     *
     * @param array<string, string> $paths
     */
    public function setAlternates(array $paths): void
    {
        $this->alternates = $paths;
    }

    /**
     * The current public page in every available language (locale => absolute URL), for the
     * language switch and the `hreflang` links; empty when the request is not a public page.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        if ($this->locale === null) {
            return [];
        }

        $query = (string) service('request')->getUri()->getQuery();
        $links = [];

        foreach (Locales::available() as $locale) {
            $links[$locale] = $this->url($this->alternates[$locale] ?? $this->path, $locale, $query);
        }

        return $links;
    }

    /**
     * Languages the current page really exists in (`hreflang`): those given by the module with
     * `setAlternates()`, or every available language when the module gave none.
     *
     * @return array<string, string> locale => absolute URL
     */
    public function hreflang(): array
    {
        $links = $this->links();

        return $this->alternates === [] ? $links : array_intersect_key($links, $this->alternates);
    }
}
