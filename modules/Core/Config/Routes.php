<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Core routes. Never queries the database.
 *
 * @var RouteCollection $routes
 */

// Theme preview (development only, Config\Theme::$preview; otherwise 404).
$routes->get('theme-preview', '\Modules\Core\Controllers\ThemePreview::show/backend');
$routes->get('theme-preview/ping', '\Modules\Core\Controllers\ThemePreview::ping');
$routes->get('theme-preview/ping-error', '\Modules\Core\Controllers\ThemePreview::pingError');
$routes->get('theme-preview/(:segment)', '\Modules\Core\Controllers\ThemePreview::show/$1');

// Minimal PWA (setting Core.pwa; otherwise 404). The service worker is served from the root.
$routes->get('manifest.webmanifest', '\Modules\Core\Controllers\Pwa::manifest');
$routes->get('sw.js', '\Modules\Core\Controllers\Pwa::serviceWorker');
$routes->get('pwa/icon-(:num).png', '\Modules\Core\Controllers\Pwa::icon/$1');

// Notifications of the logged-in user (resource core-notifications, granted to every group).
// The bell's endpoints come before the CRUD's catch-all route.
$routes->group('backend', ['namespace' => 'Modules\Core\Controllers'], static function (RouteCollection $routes): void {
    $can = ['filter' => 'can:core-notifications.list'];

    $routes->get('notifications/bell', 'Notifications::bell', $can);
    $routes->get('notifications/open/(:num)', 'Notifications::open/$1', $can);
    $routes->post('notifications/read-all', 'Notifications::readAll', $can);
    $routes->get('profile/notifications', 'NotificationPreferences::index', $can + ['as' => 'core-notification-preferences']);
    $routes->post('profile/notifications', 'NotificationPreferences::save', $can);
});

CrudFactory::routes($routes, 'core-notifications', 'backend/notifications', '\Modules\Core\Controllers\Notifications::index');

// Scheduled tasks (schedule.list / schedule.show / schedule.run for "run now").
CrudFactory::routes($routes, 'schedule', 'backend/schedule', '\Modules\Core\Controllers\Schedule::index');
