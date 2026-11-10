<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Kpapy\Resource;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the News module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Back office (`backend` chain + `can:`): the CRUDs `news-posts`, `news-mine`, `news-categories`
 * (kpcrudy routes, registered before the reader's `backend/news/read/…`), the reader screen
 * `news-read`. Public site: `news`, `news/feed.xml`, `news/category/{slug}`, `news/{slug}`.
 * API: `api/v1/news` (kpapy filter).
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'news-posts', 'backend/news/posts', '\Modules\News\Controllers\Posts::index');
CrudFactory::routes($routes, 'news-mine', 'backend/news/mine', '\Modules\News\Controllers\Posts::mine');
CrudFactory::routes($routes, 'news-categories', 'backend/news/categories', '\Modules\News\Controllers\Categories::index');

$routes->group('backend/news', ['namespace' => 'Modules\News\Controllers'], static function (RouteCollection $routes): void {
    $read = ['filter' => 'can:news-read.view'];

    $routes->get('/', 'Reader::index', $read + ['as' => 'news-read']);
    $routes->get('read/(:segment)', 'Reader::show/$1', $read);
    $routes->post('read/(:segment)/mark', 'Reader::mark/$1', $read);
});

$routes->group('news', ['namespace' => 'Modules\News\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PublicNews::index', ['as' => 'news-public']);
    $routes->get('feed.xml', 'PublicNews::feed', ['as' => 'news-feed', 'filter' => 'ratelimit:feeds']);
    $routes->get('category/(:segment)', 'PublicNews::category/$1');
    $routes->get('(:segment)', 'PublicNews::show/$1');
});

Resource::routes($routes, 'api/v1/news', '\Modules\News\Controllers\Api\Posts::index', ['filter' => 'kpapy']);
