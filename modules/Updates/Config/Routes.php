<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Updates module (discovered by CodeIgniter; never queries the database).
 *
 * - `backend/updates`: installed and latest versions and the release notes (`updates.view`);
 * - `POST backend/updates/check`: check now (`updates.check`, rate limited). The only outbound
 *   request a page makes, and only on this explicit action.
 *
 * Updating is done from the console only (`php spark app:update`).
 *
 * @var RouteCollection $routes
 */
$routes->group('backend/updates', ['namespace' => 'Modules\Updates\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Updates::index', ['as' => 'updates', 'filter' => 'can:updates.view']);
    $routes->post('check', 'Updates::check', ['filter' => ['can:updates.check', 'ratelimit:forms']]);
});
