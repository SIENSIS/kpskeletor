<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Events module (discovered by CodeIgniter; never queries the database). A disabled
 * module answers 404 (the global `moduleenabled` filter, controller namespace). D-24-01.
 *
 * Back office (`backend` chain + `can:`): the CRUDs `events-events` (`backend/events`) and
 * `events-categories` (`backend/events/categories`, registered first); the calendar
 * (`events-calendar.view`): `backend/calendar`, its JSON feed, an event's details and the
 * user's reminder. Public site: `calendar`, `calendar/feed` (public events only, rate-limited)
 * and `calendar/event/{code}`.
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'events-categories', 'backend/events/categories', '\Modules\Events\Controllers\Categories::index');
CrudFactory::routes($routes, 'events-events', 'backend/events', '\Modules\Events\Controllers\Events::index');

$routes->group('backend/calendar', ['namespace' => 'Modules\Events\Controllers'], static function (RouteCollection $routes): void {
    $view = ['filter' => 'can:events-calendar.view'];

    $routes->get('/', 'CalendarView::index', $view + ['as' => 'events-calendar']);
    $routes->get('feed', 'CalendarView::feed', $view);
    $routes->get('event/(:segment)', 'CalendarView::show/$1', $view);
    $routes->post('event/(:segment)/reminder', 'CalendarView::reminder/$1', $view);
});

$routes->group('calendar', ['namespace' => 'Modules\Events\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PublicCalendar::index', ['as' => 'events-public']);
    $routes->get('feed', 'PublicCalendar::feed', ['filter' => 'ratelimit:feeds']);
    $routes->get('event/(:segment)', 'PublicCalendar::show/$1');
});
