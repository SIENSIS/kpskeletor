<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Settings module (discovered by CodeIgniter; never queries the database).
 *
 * - `backend/settings`: the first tab the user can open;
 * - `backend/settings/security`: Settings › Security (`settings.security` + `reauth`);
 * - `backend/settings/theme`: the themes and the options of a theme (saving needs `settings.core`);
 * - `backend/settings/actions…`: test email, clear caches (`settings.update`) and publish assets
 *   (`settings.security` + `reauth`);
 * - `backend/settings/<module>`: a module's tab (saving needs `settings.<module>`, checked by the
 *   controller because a module without settings has no such permission).
 *
 * @var RouteCollection $routes
 */
$routes->group('backend/settings', ['namespace' => 'Modules\Settings\Controllers'], static function (RouteCollection $routes): void {
    $update   = ['filter' => 'can:settings.update'];
    $security = ['filter' => ['can:settings.security', 'reauth']];

    $routes->get('/', 'Settings::index', $update + ['as' => 'settings']);
    $routes->get('security', 'Settings::security', $security + ['as' => 'settings-security']);
    $routes->post('security', 'Settings::saveSecurity', $security);
    $routes->get('theme', 'Settings::theme', $update + ['as' => 'settings-theme']);
    $routes->post('theme', 'Settings::saveTheme', $update);
    $routes->get('actions', 'Actions::index', $update + ['as' => 'settings-actions']);
    $routes->post('actions/email', 'Actions::testEmail', $update);
    $routes->post('actions/caches', 'Actions::clearCaches', $update);
    $routes->post('actions/assets', 'Actions::publishAssets', $security);
    $routes->get('(:segment)', 'Settings::module/$1', $update + ['as' => 'settings-module']);
    $routes->post('(:segment)', 'Settings::saveModule/$1', $update);
});
