<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use CodeIgniter\Router\RouteCollection;
use Throwable;

/**
 * Routes as menu targets: the path of a route name (routes with parameters cannot be menu
 * targets) and the permissions the `can:` filters of the route answering a path require, so a
 * menu link is shown only to users who would not get a 403 (docs/specification.md, "Modules" ›
 * "Menus": "the editor cannot make a link visible to users who would get a 403").
 *
 * `can:a,b` needs one of them (`any`), `can:all,a,b` all of them (`all`).
 */
final class RouteAccess
{
    private readonly RouteCollection $routes;

    public function __construct(?RouteCollection $routes = null)
    {
        $this->routes = $routes ?? service('routes');
    }

    /** The path (no leading slash) of a GET route name without parameters, else null. */
    public function path(string $name): ?string
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9_.:\\\\-]+$/', $name) !== 1 || $this->routes->getRoutes('GET') === []) {
            return null;
        }

        try {
            $path = route_to($name);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        $base = trim((string) parse_url(site_url(), PHP_URL_PATH), '/');

        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = trim(substr($path, strlen($base)), '/');
        }

        return $this->access($path) === null ? null : $path;
    }

    /**
     * What the GET route answering a path requires, or null when no route answers it.
     *
     * @return array{all: list<string>, any: list<string>}|null
     */
    public function access(string $path): ?array
    {
        $path = trim($path, '/');

        foreach ($this->routes->getRoutes('GET') as $from => $handler) {
            $from    = (string) $from;
            $pattern = trim($from, '/');

            if ($pattern !== $path && @preg_match('#^' . str_replace('#', '\#', $pattern) . '$#u', $path) !== 1) {
                continue;
            }

            if ($pattern === '(.*)' || $pattern === '') {
                if ($path !== $pattern) {
                    continue; // a catch-all is not a menu target
                }
            }

            return self::requirements($this->routes->getFiltersForRoute($from, 'GET'));
        }

        return $path === '' ? ['all' => [], 'any' => []] : null;
    }

    /**
     * @param list<string> $filters
     *
     * @return array{all: list<string>, any: list<string>}
     */
    public static function requirements(array $filters): array
    {
        $all = [];
        $any = [];

        foreach ($filters as $filter) {
            if (! str_starts_with($filter, 'can:')) {
                continue;
            }

            $arguments = array_values(array_filter(array_map('trim', explode(',', substr($filter, 4))), static fn (string $a): bool => $a !== ''));

            if (($arguments[0] ?? '') === 'all') {
                $all = [...$all, ...array_slice($arguments, 1)];
            } elseif (count($arguments) === 1) {
                $all[] = $arguments[0];
            } elseif ($any === []) {
                $any = $arguments;
            } else {
                $all = [...$all, ...$arguments]; // a second "any" filter: stricter, never looser
            }
        }

        return ['all' => array_values(array_unique($all)), 'any' => $any];
    }
}
