<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Menus module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Public menus:
 * - `backend/menus/public`: the CRUD of the menus (kpcrudy, `menus-public.*`);
 * - `backend/menus/items/<menu>[/…]`: the tree editor of a menu's items and the item form
 *   (`menus-public.edit`).
 *
 * Back-office menu (`backend/menus/backend[/…]`): the editor (`menus-backend.view`), the order
 * and the retouches of entries and custom groups (`.customise`), custom links (`.links`), resets
 * and orphaned customisations (`.reset`). The scope (everyone or a group) is the `group` field.
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'menus-public', 'backend/menus/public', '\Modules\Menus\Controllers\PublicMenus::index');

$routes->group('backend/menus/items', ['namespace' => 'Modules\Menus\Controllers', 'filter' => 'can:menus-public.edit'], static function (RouteCollection $routes): void {
    $routes->get('(:num)', 'MenuItems::index/$1', ['as' => 'menus-items']);
    $routes->post('(:num)/order', 'MenuItems::order/$1');
    $routes->get('(:num)/new', 'MenuItems::create/$1');
    $routes->get('(:num)/edit/(:num)', 'MenuItems::edit/$1/$2');
    $routes->post('(:num)/save', 'MenuItems::save/$1');
    $routes->post('(:num)/delete/(:num)', 'MenuItems::delete/$1/$2');
});

$routes->group('backend/menus/backend', ['namespace' => 'Modules\Menus\Controllers'], static function (RouteCollection $routes): void {
    $customise = ['filter' => 'can:menus-backend.customise'];
    $links     = ['filter' => 'can:menus-backend.links'];
    $reset     = ['filter' => 'can:menus-backend.reset'];

    $routes->get('/', 'BackendMenu::index', ['as' => 'menus-backend', 'filter' => 'can:menus-backend.view']);
    $routes->post('order', 'BackendMenu::order', $customise);
    $routes->get('entry', 'BackendMenu::entry', $customise);
    $routes->post('entry', 'BackendMenu::saveEntry', $customise);
    $routes->get('group/new', 'CustomLinks::createGroup', $customise);
    $routes->get('link/new', 'CustomLinks::createLink', $links);
    $routes->get('custom/(:num)', 'CustomLinks::edit/$1', ['filter' => 'can:menus-backend.customise,menus-backend.links']);
    $routes->post('custom/save', 'CustomLinks::save', ['filter' => 'can:menus-backend.customise,menus-backend.links']);
    $routes->post('custom/(:num)/delete', 'CustomLinks::delete/$1', ['filter' => 'can:menus-backend.customise,menus-backend.links']);
    $routes->post('reset', 'BackendMenu::reset', $reset);
    $routes->post('orphans', 'BackendMenu::deleteOrphans', $reset);
});
