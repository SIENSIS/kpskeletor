<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Dashboard module (discovered by CodeIgniter; never queries the database).
 *
 * - `backend`: the home (`dashboard.view`);
 * - `backend/dashboard/customize` (GET form without JavaScript), `backend/dashboard/layout`
 *   (POST: the user's order, sizes and hidden widgets), `backend/dashboard/layout/reset` (POST:
 *   restore the group layout): `dashboard.view`;
 * - `backend/dashboard/widgets/<id>/data`: data of an async widget (`dashboard.view` + the
 *   `widgets` rate limit; 403 unless the user can see the widget);
 * - `backend/dashboard/groups[/<group>]`: the group layouts (`dashboard.configure`).
 *
 * @var RouteCollection $routes
 */
$routes->get('backend', '\Modules\Dashboard\Controllers\Dashboard::index', ['as' => 'dashboard', 'filter' => 'can:dashboard.view']);

$routes->group('backend/dashboard', ['namespace' => 'Modules\Dashboard\Controllers'], static function (RouteCollection $routes): void {
    $view      = ['filter' => 'can:dashboard.view'];
    $configure = ['filter' => 'can:dashboard.configure'];

    $routes->get('customize', 'Dashboard::customize', $view + ['as' => 'dashboard-customize']);
    $routes->post('layout', 'Dashboard::save', $view);
    $routes->post('layout/reset', 'Dashboard::reset', $view);
    $routes->get('widgets/(:segment)/data', 'WidgetData::show/$1', ['filter' => ['can:dashboard.view', 'ratelimit:widgets']]);

    $routes->get('groups', 'GroupLayout::index', $configure + ['as' => 'dashboard-groups']);
    $routes->get('groups/(:segment)', 'GroupLayout::show/$1', $configure);
    $routes->post('groups/(:segment)', 'GroupLayout::save/$1', $configure);
});
