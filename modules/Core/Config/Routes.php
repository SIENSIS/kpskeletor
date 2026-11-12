<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Core routes. Never queries the database.
 *
 * @var RouteCollection $routes
 */

// Web installer: only before installation (404 once `.env` or writable/install.lock exists, or
// when the database has tables). Every other page redirects here meanwhile (InstallGuard).
$routes->get('install', '\Modules\Core\Controllers\WebInstaller::index');
$routes->post('install', '\Modules\Core\Controllers\WebInstaller::install');

// Theme preview (development only, Config\Theme::$preview; otherwise 404).
$routes->get('theme-preview', '\Modules\Core\Controllers\ThemePreview::show/backend');
$routes->get('theme-preview/ping', '\Modules\Core\Controllers\ThemePreview::ping');
$routes->get('theme-preview/ping-error', '\Modules\Core\Controllers\ThemePreview::pingError');
$routes->get('theme-preview/(:segment)', '\Modules\Core\Controllers\ThemePreview::show/$1');

// Minimal PWA (setting Core.pwa; otherwise 404). The service worker is served from the root.
$routes->get('manifest.webmanifest', '\Modules\Core\Controllers\Pwa::manifest');
$routes->get('sw.js', '\Modules\Core\Controllers\Pwa::serviceWorker');
$routes->get('pwa/icon-(:num).png', '\Modules\Core\Controllers\Pwa::icon/$1');

// Private files of the Storage service: served only after Config\Storage::$accessRule allows it
// (otherwise 404), with the stored MIME type, nosniff and `attachment` unless image or PDF.
$routes->get('files/private/(:segment)', '\Modules\Core\Controllers\PrivateFiles::show/$1');

// File picker of module fields: a signed upload token issued with the field grants the upload
// (no files.* permission); signed-in users only (checked by the controller), uploads rate limited.
$routes->post('files/picker/upload', '\Modules\Core\Controllers\FilePicker::upload', ['filter' => 'ratelimit:uploads']);
$routes->get('files/picker/browse', '\Modules\Core\Controllers\FilePicker::browse');

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
