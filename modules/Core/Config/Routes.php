<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

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
