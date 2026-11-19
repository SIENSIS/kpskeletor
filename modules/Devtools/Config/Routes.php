<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Devtools\Filters\LocalOnly;
use Modules\Devtools\Routing\DevtoolsRoutes;

/**
 * Routes of the Devtools module, **only in the development environment** (lock 1 of
 * docs/devtools.md, D-41-01): a constant, no database. In any other environment no Devtools URL
 * exists (404). For a web request from another machine or with a foreign `Host` they are not
 * registered either, so it answers 404 before the back office's login redirect (lock 4; the
 * `devtools` route filter checks the same again). The console (`php spark routes`,
 * `permissions:check`) sees them. The routes and their filters are in DevtoolsRoutes.
 *
 * @var RouteCollection $routes
 */
if (ENVIRONMENT === 'development') {
    $server = service('superglobals');
    $remote = $server->server('REMOTE_ADDR');
    $host   = $server->server('HTTP_HOST');

    if (is_cli() || LocalOnly::isLocal(is_string($remote) ? $remote : null, is_string($host) ? $host : null)) {
        DevtoolsRoutes::define($routes);
    }
}
