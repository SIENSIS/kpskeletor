<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Pages module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Back office: the CRUD `pages` (`backend/pages`, `backend` chain + `can:pages.list`; preview,
 * revisions, restore and duplicate are custom operations of the CRUD).
 * Public site: `sitemap.xml` and the pages themselves at their nested slugs (`about/team`), a
 * catch-all with the **lowest priority** (`Config\Routing::$prioritize`) so every other route
 * wins whatever the discovery order; it never matches `backend/…` or `api/…`, and only valid
 * slugs (other paths get the router's 404). `/` is answered through the Core's `homePage` event
 * (Config/Events.php).
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'pages', 'backend/pages', '\Modules\Pages\Controllers\Pages::index');

$routes->addPlaceholder('pagepath', '(?!(?:api|backend)(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*');

$routes->get('sitemap.xml', '\Modules\Pages\Controllers\Sitemap::index', ['as' => 'pages-sitemap', 'filter' => 'ratelimit:feeds']);
$routes->get('(:pagepath)', '\Modules\Pages\Controllers\PublicPages::show/$1', ['as' => 'pages-public', 'priority' => 1]);
