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

// Public language switch (docs/i18n.md, "Choosing the language"): stores the choice in a cookie
// (and, through the `localeChosen` event, in the signed-in user's profile), then goes back.
$routes->post('language', '\Modules\Core\Controllers\LanguageSwitch::set', ['as' => 'language-switch']);

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

// The same notifications in the API (D-26-01; token required).
\Kpapy\Resource::routes($routes, 'api/v1/notifications', '\Modules\Core\Controllers\Api\Notifications::index', ['filter' => 'kpapy']);

// Scheduled tasks (schedule.list / schedule.show / schedule.run for "run now").
CrudFactory::routes($routes, 'schedule', 'backend/schedule', '\Modules\Core\Controllers\Schedule::index');

/*
 * The Modules screen (docs/installing-modules.md). Uploads, approvals and uninstall requests need a recent
 * re-authentication; the installs run from the console (`php spark modules:install --pending`).
 */
$routes->group('backend/modules', ['namespace' => 'Modules\Core\Controllers'], static function (RouteCollection $routes): void {
    $list      = ['filter' => 'can:modules.list'];
    $toggle    = ['filter' => 'can:modules.toggle'];
    $upload    = ['filter' => ['can:modules.upload', 'reauth']];
    $uninstall = ['filter' => ['can:modules.uninstall', 'reauth']];

    $routes->get('/', 'Modules::index', $list + ['as' => 'modules']);
    $routes->post('enable/(:segment)', 'Modules::enable/$1', $toggle);
    $routes->post('disable/(:segment)', 'Modules::disable/$1', $toggle);
    $routes->post('demo/(:segment)', 'Modules::demo/$1', ['filter' => ['can:modules.demo', 'ratelimit:forms']]);
    $routes->post('sync', 'Modules::sync', ['filter' => ['can:modules.sync', 'ratelimit:forms']]);
    $routes->get('packages', 'Modules::packages', $list + ['as' => 'modules-packages']);
    $routes->get('packages/upload', 'Modules::uploadForm', $upload);
    $routes->post('packages/upload', 'Modules::upload', ['filter' => ['can:modules.upload', 'reauth', 'ratelimit:uploads']]);
    $routes->get('packages/(:num)', 'Modules::package/$1', $list);
    $routes->post('packages/(:num)/approve', 'Modules::approve/$1', ['filter' => ['can:modules.upload', 'reauth', 'ratelimit:forms']]);
    $routes->post('packages/(:num)/discard', 'Modules::discard/$1', ['filter' => ['can:modules.upload,modules.uninstall', 'ratelimit:forms']]);
    $routes->get('uninstall/(:segment)', 'Modules::uninstallForm/$1', $uninstall);
    $routes->post('uninstall/(:segment)', 'Modules::uninstall/$1', ['filter' => ['can:modules.uninstall', 'reauth', 'ratelimit:forms']]);
    $routes->get('themes', 'Themes::index', $list + ['as' => 'modules-themes']);
    $routes->post('themes/activate', 'Themes::activate', $toggle);
    $routes->get('themes/uninstall/(:segment)', 'Themes::uninstallForm/$1', $uninstall);
    $routes->post('themes/uninstall/(:segment)', 'Themes::uninstall/$1', ['filter' => ['can:modules.uninstall', 'reauth', 'ratelimit:forms']]);
});

/*
 * The Security status page (docs/security.md, "Production checklist"): the cached results of
 * `security:check`, "check again" and the external probe (a deliberate action: re-authentication).
 */
$routes->group('backend/security', ['namespace' => 'Modules\Core\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'SecurityStatus::index', ['as' => 'security-status', 'filter' => 'can:security.check']);
    $routes->post('refresh', 'SecurityStatus::refresh', ['filter' => ['can:security.check', 'ratelimit:forms']]);
    $routes->get('probe', 'SecurityStatus::probeForm', ['filter' => ['can:security.check', 'reauth']]);
    $routes->post('probe', 'SecurityStatus::probe', ['filter' => ['can:security.check', 'reauth', 'ratelimit:forms']]);
});
