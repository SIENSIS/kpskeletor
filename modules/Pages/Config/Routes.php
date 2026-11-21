<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Pages module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Back office: the CRUD `pages` (`backend/pages`, `backend` chain + `can:pages.list`; preview,
 * revisions, restore and duplicate are custom operations of the CRUD) and the section editor
 * `backend/pages/<id>/sections[/…]` (`can:pages.edit`, D-45-01).
 * Public site: `sitemap.xml` and the pages themselves at their nested slugs (`about/team`), a
 * catch-all with the **lowest priority** (`Config\Routing::$prioritize`) so every other route
 * wins whatever the discovery order; it never matches `backend/…` or `api/…`, and only valid
 * slugs (other paths get the router's 404). `/` is answered through the Core's `homePage` event
 * (Config/Events.php).
 *
 * @var RouteCollection $routes
 */
// The section editor of a page (D-45-01): `pages.edit` here, the page's record rules in the
// controller. Declared before the CRUD so that `backend/pages/(:any)` does not shadow it.
$routes->group(backend_path('pages'), ['namespace' => 'Modules\Pages\Controllers', 'filter' => 'can:pages.edit'], static function (RouteCollection $routes): void {
    $routes->get('(:num)/sections', 'Sections::index/$1', ['as' => 'pages-sections']);
    $routes->get('(:num)/sections/add', 'Sections::gallery/$1');
    $routes->get('(:num)/sections/new/(:segment)', 'Sections::create/$1/$2');
    $routes->get('(:num)/sections/(:num)', 'Sections::edit/$1/$2');
    $routes->post('(:num)/sections/save', 'Sections::save/$1');
    $routes->post('(:num)/sections/order', 'Sections::order/$1');
    $routes->post('(:num)/sections/layout', 'Sections::switchLayout/$1');
    $routes->post('(:num)/sections/(:num)/duplicate', 'Sections::duplicate/$1/$2');
    $routes->post('(:num)/sections/(:num)/hide', 'Sections::hide/$1/$2');
    $routes->post('(:num)/sections/(:num)/delete', 'Sections::delete/$1/$2');
});

CrudFactory::routes($routes, 'pages', backend_path('pages'), '\Modules\Pages\Controllers\Pages::index');

$routes->addPlaceholder('pagepath', '(?!(?:api|backend|' . preg_quote(backend_path(), '#') . ')(?:/|$))[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*');

$routes->get('sitemap.xml', '\Modules\Pages\Controllers\Sitemap::index', ['as' => 'pages-sitemap', 'filter' => 'ratelimit:feeds']);
$routes->get('(:pagepath)', '\Modules\Pages\Controllers\PublicPages::show/$1', ['as' => 'pages-public', 'priority' => 1]);
