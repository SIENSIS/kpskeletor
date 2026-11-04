<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the {{Name}} module (discovered by CodeIgniter). Every back-office route declares its
 * `can:` filter; the route name is "<module>-<resource>".
 *
 * @var RouteCollection $routes
 */
$routes->group('backend/{{name}}', ['namespace' => 'Modules\{{Name}}\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index', ['as' => '{{name}}-main', 'filter' => 'can:{{name}}-main.view']);
});
