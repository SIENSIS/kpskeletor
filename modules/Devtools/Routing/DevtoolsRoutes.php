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

    /**
     * Filters of the POST routes that only change the user's draft in the session (the code
     * generator's form buttons, loading a definition): no file is written and no command runs,
     * so no re-authentication (a POST is never replayed after it: the edits would be lost) and
     * no `forms` rate limit (10 an hour; every "add a column" is a POST) (D-42-02). Listed one by
     * one in DRAFT_ROUTES; every other POST is WRITE.
     */
    public const DRAFT = ['devtools', 'can:devtools.view'];

    /** The DRAFT routes (paths under the Devtools prefix). */
    public const DRAFT_ROUTES = ['generator', 'generator/load'];

    /** Filters of every route that writes files or runs commands (POST). */
    public const WRITE = ['devtools', 'can:devtools.write', 'reauth', 'ratelimit:forms'];

    public static function define(RouteCollection $routes): void
    {
        $routes->group(backend_path('devtools'), ['namespace' => 'Modules\Devtools\Controllers'], static function (RouteCollection $routes): void {
            $view = ['filter' => self::VIEW];

            $routes->get('/', 'Home::index', $view + ['as' => 'devtools']);
            $routes->get('recipes', 'Recipes::index', $view + ['as' => 'devtools-recipes']);
            $routes->get('docs/(:segment)', 'Docs::show/$1', $view + ['as' => 'devtools-docs']);

            // Code generator (session 42): the draft and the preview are views, writing is WRITE.
            $routes->get('generator', 'Generator::index', $view + ['as' => 'devtools-generator']);
            $routes->post('generator', 'Generator::update', ['filter' => self::DRAFT]);
            $routes->get('generator/preview', 'Generator::preview', $view + ['as' => 'devtools-generator-preview']);
            $routes->get('generator/definition.json', 'Generator::download', $view);
            $routes->post('generator/load', 'Generator::load', ['filter' => self::DRAFT]);
            $routes->post('generator/write', 'Generator::write', ['filter' => self::WRITE]);
            $routes->get('generator/result', 'Generator::result', $view);

            // Packages (session 43, D-43-01): building and signing are WRITE; the page after a
            // signature runs the cleanup and ends the session.
            $routes->get('packages', 'Packages::index', $view + ['as' => 'devtools-packages']);
            $routes->post('packages/build', 'Packages::build', ['filter' => self::WRITE]);
            $routes->get('packages/download/(:segment)', 'Packages::download/$1', $view);
            $routes->get('packages/sign', 'Packages::signForm', $view);
            $routes->post('packages/sign', 'Packages::sign', ['filter' => self::WRITE]);
            $routes->get('packages/signed', 'Packages::signed', $view);
        });
    }
}
