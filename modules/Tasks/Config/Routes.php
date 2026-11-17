<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Kpapy\Resource;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Tasks tutorial module (discovered by CodeIgniter; never queries the database). A
 * disabled module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Back office (`backend` chain + `can:`): the CRUDs `tasks-tasks`, `tasks-projects` and
 * `tasks-requests` (registered before the board's `backend/tasks/…`), the board `tasks-board`
 * (`backend/tasks`, its JSON data and the move endpoint). Public site: `tasks` (public to-do
 * tasks), `tasks/project/{slug}`, the request form `tasks/request` (captcha, `ratelimit:forms`).
 * API: `api/v1/tasks/tasks`, `api/v1/tasks/projects` (kpapy filter).
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'tasks-tasks', 'backend/tasks/tasks', '\Modules\Tasks\Controllers\Tasks::index');
CrudFactory::routes($routes, 'tasks-projects', 'backend/tasks/projects', '\Modules\Tasks\Controllers\Projects::index');
CrudFactory::routes($routes, 'tasks-requests', 'backend/tasks/requests', '\Modules\Tasks\Controllers\Requests::index');

$routes->group('backend/tasks', ['namespace' => 'Modules\Tasks\Controllers'], static function (RouteCollection $routes): void {
    $board = ['filter' => 'can:tasks-board.view'];

    $routes->get('/', 'Board::index', $board + ['as' => 'tasks-board']);
    $routes->get('board/data', 'Board::data', $board);
    $routes->post('board/(:segment)/move', 'Board::move/$1', $board);
});

$routes->group('tasks', ['namespace' => 'Modules\Tasks\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PublicTasks::index', ['as' => 'tasks-public']);
    $routes->get('project/(:segment)', 'PublicTasks::project/$1');
    $routes->get('request', 'RequestForm::show', ['as' => 'tasks-request']);
    $routes->post('request', 'RequestForm::send', ['filter' => 'ratelimit:forms']);
    $routes->get('request/captcha', 'RequestForm::captcha');
});

Resource::routes($routes, 'api/v1/tasks/tasks', '\Modules\Tasks\Controllers\Api\Tasks::index', ['filter' => 'kpapy']);
Resource::routes($routes, 'api/v1/tasks/projects', '\Modules\Tasks\Controllers\Api\Projects::index', ['filter' => 'kpapy']);
