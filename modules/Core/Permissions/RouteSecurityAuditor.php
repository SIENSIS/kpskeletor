<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Router\Router;

/**
 * Default deny on routes (docs/specification.md, "RBAC" › "Enforcement on routes"; D-00-30):
 *
 * - every route under `backend/` has a `can:` filter with at least one permission, and every
 *   permission it names exists in the catalogue (when one is given);
 * - every route under `api/v1/` has kpapy's filter: `kpapy`, `kpapy:optional` or `kpapy:hmac`;
 *   `kpapy:login` only on the `auth/token*` login routes; `kpapy:public` only for GET (and the
 *   HEAD/OPTIONS that go with it).
 *
 * Used by `RoutesSecurityTest` (CI) and `php spark permissions:check`.
 */
final class RouteSecurityAuditor
{
    /** kpapy filter arguments allowed on any API route */
    private const API_MODES = ['optional', 'hmac'];

    /**
     * @param list<string>|null $catalogue Known permission names (null: names are not checked)
     *
     * @return list<string> One line per problem: "<VERB> <route>: <problem>"
     */
    public function audit(RouteCollection $routes, ?array $catalogue = null): array
    {
        $problems = [];

        foreach ($this->routes($routes) as [$verb, $from]) {
            $filters = $routes->getFiltersForRoute($from, $verb);
            $path    = ltrim($from, '/');
            $label   = $verb . ' ' . $path;

            if ($path === 'backend' || str_starts_with($path, 'backend/')) {
                foreach ($this->backendProblems($filters, $catalogue) as $problem) {
                    $problems[] = $label . ': ' . $problem;
                }
            } elseif ($path === 'api/v1' || str_starts_with($path, 'api/v1/')) {
                foreach ($this->apiProblems($verb, substr($path, strlen('api/v1/')), $filters) as $problem) {
                    $problems[] = $label . ': ' . $problem;
                }
            }
        }

        return $problems;
    }

    /**
     * Every (verb, route) pair of the collection, CLI routes excluded.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function routes(RouteCollection $routes): array
    {
        $pairs = [];

        foreach (Router::HTTP_METHODS as $verb) {
            foreach (array_keys($routes->getRoutes($verb)) as $from) {
                $pairs[] = [$verb, (string) $from];
            }
        }

        return $pairs;
    }

    /**
     * The permissions named by the `can:` filters of a route (`all` excluded).
     *
     * @param list<string> $filters
     *
     * @return list<string>|null null when the route has no `can:` filter
     */
    public static function canPermissions(array $filters): ?array
    {
        $permissions = null;

        foreach ($filters as $filter) {
            if (! str_starts_with($filter, 'can:')) {
                continue;
            }

            $arguments   = array_filter(array_map('trim', explode(',', substr($filter, 4))), static fn (string $a): bool => $a !== '' && $a !== 'all');
            $permissions = [...($permissions ?? []), ...array_values($arguments)];
        }

        return $permissions;
    }

    /**
     * @param list<string>      $filters
     * @param list<string>|null $catalogue
     *
     * @return list<string>
     */
    private function backendProblems(array $filters, ?array $catalogue): array
    {
        $permissions = self::canPermissions($filters);

        if ($permissions === null || $permissions === []) {
            return ['no can: filter with a permission'];
        }

        if ($catalogue === null) {
            return [];
        }

        $problems = [];

        foreach ($permissions as $permission) {
            if (! in_array(strtolower($permission), $catalogue, true)) {
                $problems[] = "can: names {$permission}, which is not in the permission catalogue";
            }
        }

        return $problems;
    }

    /**
     * @param list<string> $filters
     *
     * @return list<string>
     */
    private function apiProblems(string $verb, string $path, array $filters): array
    {
        $modes = [];

        foreach ($filters as $filter) {
            if ($filter === 'kpapy') {
                $modes[] = '';
            } elseif (str_starts_with($filter, 'kpapy:')) {
                $modes[] = trim(explode(',', substr($filter, 6))[0]);
            }
        }

        if ($modes === []) {
            return ['no kpapy filter'];
        }

        $problems = [];

        foreach ($modes as $mode) {
            if ($mode === '' || in_array($mode, self::API_MODES, true)) {
                continue;
            }

            if ($mode === 'login') {
                if (! str_starts_with($path, 'auth/token')) {
                    $problems[] = 'kpapy:login is only for the auth/token* login routes';
                }

                continue;
            }

            if ($mode === 'public') {
                if (! in_array(strtoupper($verb), ['GET', 'HEAD', 'OPTIONS'], true)) {
                    $problems[] = 'kpapy:public is only for GET routes';
                }

                continue;
            }

            $problems[] = "unknown kpapy mode \"{$mode}\"";
        }

        return $problems;
    }
}
