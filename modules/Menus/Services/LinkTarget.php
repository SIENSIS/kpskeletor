<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

/**
 * The target of a menu item or custom link: a `type` and a `value`.
 *
 * - `url`: an external `https://`/`http://` URL or an internal path (`UrlValidator`);
 * - `route`: the name of a GET route without parameters;
 * - a module link type (`pages.page`, `news.category`…): one of the type's choices.
 *
 * `resolve()` gives the address and what the user must hold to open it: internal paths and
 * routes take the `can:` permissions of the route that answers them (`RouteAccess`), so a menu
 * never shows a link that would answer 403.
 */
final class LinkTarget
{
    public const URL   = 'url';
    public const ROUTE = 'route';

    public function __construct(private readonly LinkTypes $types = new LinkTypes(), private readonly RouteAccess $routes = new RouteAccess())
    {
    }

    public function types(): LinkTypes
    {
        return $this->types;
    }

    /**
     * Every type with its translated name.
     *
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        return [self::URL => lang('Menus.types.url'), self::ROUTE => lang('Menus.types.route')] + $this->types->labels();
    }

    /**
     * Why a target cannot be saved, or null when it can: internal paths and route names must be
     * answered by a route (a link must lead somewhere).
     */
    public function error(string $type, string $value): ?string
    {
        $value = trim($value);

        if ($type === self::URL) {
            $url = UrlValidator::check($value);

            if ($url === null) {
                return lang('Menus.errors.url');
            }

            if (UrlValidator::isInternal($url) && $this->routes->access(UrlValidator::path($url)) === null) {
                return lang('Menus.errors.path');
            }

            return null;
        }

        if ($type === self::ROUTE) {
            return $this->routes->path($value) === null ? lang('Menus.errors.route') : null;
        }

        $linkType = $this->types->get($type);

        if ($linkType === null) {
            return lang('Menus.errors.type');
        }

        return array_key_exists($value, $linkType->options()) ? null : lang('Menus.errors.choice');
    }

    /**
     * The address of a target and the permissions it needs, or null when it is gone (an unknown
     * route, a deleted page, a disabled module's type, an address no longer valid).
     *
     * `href` is an absolute URL, or an internal path without the leading slash when `$relative`.
     *
     * @return array{href: string, internal: bool, all: list<string>, any: list<string>}|null
     */
    public function resolve(string $type, string $value, bool $relative = false): ?array
    {
        if ($type === self::URL) {
            $url = UrlValidator::check($value);

            if ($url === null) {
                return null;
            }

            if (! UrlValidator::isInternal($url)) {
                return ['href' => $url, 'internal' => false, 'all' => [], 'any' => []];
            }

            $access = $this->routes->access(UrlValidator::path($url));

            if ($access === null) {
                return null;
            }

            return ['href' => $relative ? ltrim($url, '/') : site_url(ltrim($url, '/')), 'internal' => true] + $access;
        }

        if ($type === self::ROUTE) {
            $path = $this->routes->path($value);

            if ($path === null) {
                return null;
            }

            $access = $this->routes->access($path) ?? ['all' => [], 'any' => []];

            return ['href' => $relative ? $path : site_url($path), 'internal' => true] + $access;
        }

        $url = $this->types->get($type)?->url($value);

        return $url === null || UrlValidator::check($url) === null ? null : ['href' => $url, 'internal' => false, 'all' => [], 'any' => []];
    }
}
