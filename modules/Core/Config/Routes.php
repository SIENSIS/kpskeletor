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
$routes->get('theme-preview/(:segment)', '\Modules\Core\Controllers\ThemePreview::show/$1');
