<?php

declare(strict_types=1);

namespace Modules\Devtools\Routing;

use CodeIgniter\Router\RouteCollection;

/**
 * The Devtools routes (registered by `Config/Routes.php` only in development). Every route runs
 * the `devtools` filter first (loopback only) and its permission; a route that writes files or
 * runs commands uses WRITE: `devtools.write`, re-authentication and the forms rate limit
 * (D-41-03). `DevtoolsRoutesTest` checks every Devtools route against these lists.
 */
final class DevtoolsRoutes
{
    /** Filters of every screen. */
    public const VIEW = ['devtools', 'can:devtools.view'];

    /** Filters of every route that writes files or runs commands (POST). */
    public const WRITE = ['devtools', 'can:devtools.write', 'reauth', 'ratelimit:forms'];

    public static function define(RouteCollection $routes): void
    {
        $routes->group(backend_path('devtools'), ['namespace' => 'Modules\Devtools\Controllers'], static function (RouteCollection $routes): void {
            $view = ['filter' => self::VIEW];

            $routes->get('/', 'Home::index', $view + ['as' => 'devtools']);
            $routes->get('recipes', 'Recipes::index', $view + ['as' => 'devtools-recipes']);
            $routes->get('docs/(:segment)', 'Docs::show/$1', $view + ['as' => 'devtools-docs']);
        });
    }
}
