<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Files module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * - `backend/files`: the file manager (`files.list`);
 * - `backend/files/api/*`: its JSON endpoints. Every route needs `files.list` plus the RBAC
 *   permission of its operation; the folder ACL is checked in the controller for each folder or
 *   file. Uploads are rate limited (`ratelimit:uploads`).
 *
 * @var RouteCollection $routes
 */
$routes->get('backend/files', '\Modules\Files\Controllers\FileManager::index', ['as' => 'files', 'filter' => 'can:files.list']);

$routes->group('backend/files/api', ['namespace' => 'Modules\Files\Controllers'], static function (RouteCollection $routes): void {
    $list    = ['filter' => 'can:all,files.list'];
    $upload  = ['filter' => ['can:all,files.list,files.upload', 'ratelimit:uploads']];
    $edit    = ['filter' => 'can:all,files.list,files.edit'];
    $delete  = ['filter' => 'can:all,files.list,files.delete'];
    $folders = ['filter' => 'can:all,files.list,files.folders'];
    $acl     = ['filter' => 'can:all,files.list,files.acl'];

    $routes->get('tree', 'FileApi::tree', $list);
    $routes->get('list', 'FileApi::list', $list);
    $routes->get('trash', 'FileApi::trash', $list);
    $routes->get('file/(:num)', 'FileApi::show/$1', $list);
    $routes->get('file/(:num)/download', 'FileApi::download/$1', $list);
    $routes->get('file/(:num)/preview', 'FileApi::preview/$1', $list);
    $routes->get('file/(:num)/thumbnail', 'FileApi::thumbnail/$1', $list);
    $routes->post('upload', 'FileApi::upload', $upload);
    $routes->post('file/(:num)/update', 'FileApi::update/$1', $edit);
    $routes->post('file/(:num)/move', 'FileApi::move/$1', $edit);
    $routes->post('file/(:num)/delete', 'FileApi::delete/$1', $delete);
    $routes->post('file/(:num)/restore', 'FileApi::restore/$1', $delete);
    $routes->post('file/(:num)/purge', 'FileApi::purge/$1', $delete);
    $routes->post('folder', 'FileApi::createFolder', $folders);
    $routes->post('folder/rename', 'FileApi::renameFolder', $folders);
    $routes->post('folder/delete', 'FileApi::deleteFolder', $folders);
    $routes->get('acl', 'FileApi::acl', $acl);
    $routes->post('acl', 'FileApi::saveAcl', $acl);
});
