<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Users module (discovered by CodeIgniter; never queries the database).
 *
 * - `backend/users/access/<id>`: a user's groups and individual permissions
 *   (`users-users.access` + `reauth`);
 * - `backend/users/permissions`: the permission matrix (`users-matrix.view`; saving needs
 *   `users-matrix.edit` + `reauth`);
 * - `backend/users/groups…`: the groups (kpcrudy; changes need a re-authentication, checked by
 *   the controller);
 * - `backend/users…`: the users (kpcrudy). Registered last so the paths above are not taken as
 *   operations of the users' CRUD.
 *
 * @var RouteCollection $routes
 */
$routes->group('backend/users', ['namespace' => 'Modules\Users\Controllers'], static function (RouteCollection $routes): void {
    $access = ['filter' => ['can:users-users.access', 'reauth']];

    $routes->get('access/(:num)', 'Access::show/$1', $access + ['as' => 'users-access']);
    $routes->post('access/(:num)', 'Access::save/$1', $access);

    $routes->get('permissions', 'Matrix::index', ['as' => 'users-matrix', 'filter' => 'can:users-matrix.view']);
    $routes->post('permissions', 'Matrix::save', ['filter' => ['can:users-matrix.edit', 'reauth']]);
});

CrudFactory::routes($routes, 'users-groups', 'backend/users/groups', '\Modules\Users\Controllers\Groups::index');
CrudFactory::routes($routes, 'users-users', 'backend/users', '\Modules\Users\Controllers\Users::index');
