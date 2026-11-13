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
 * Session 25 (iCal, D-25-01): one-off exports `…/event/{code}/export.ics`, the calendar toggles
 * (`POST backend/calendar/calendars`), the subscription page `backend/calendar/subscription`, and
 * the feeds `ical/user/<secret>.ics` and `ical/public/<all|category slug>.ics`.
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
    $routes->get('event/(:segment)/export.ics', 'CalendarView::export/$1', $view);
    $routes->post('calendars', 'CalendarView::calendars', $view);

    // The personal iCal feed (session 25): creating or regenerating its secret needs re-authentication.
    $routes->get('subscription', 'Subscription::index', $view + ['as' => 'events-subscription']);
    $routes->post('subscription/generate', 'Subscription::generate', ['filter' => ['can:events-calendar.view', 'reauth']]);
    $routes->post('subscription/revoke', 'Subscription::revoke', $view);
    $routes->post('subscription/filters', 'Subscription::filters', $view);
});

$routes->group('calendar', ['namespace' => 'Modules\Events\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PublicCalendar::index', ['as' => 'events-public']);
    $routes->get('feed', 'PublicCalendar::feed', ['filter' => 'ratelimit:feeds']);
    $routes->get('event/(:segment)', 'PublicCalendar::show/$1');
    $routes->get('event/(:segment)/export.ics', 'PublicCalendar::export/$1', ['filter' => 'ratelimit:feeds']);
});

// iCal feeds (no session or login: calendar clients poll them). Rate limit `feeds` per IP here and
// per secret in the controller.
$routes->group('ical', ['namespace' => 'Modules\Events\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('user/(:segment)', 'IcalFeed::personal/$1', ['filter' => 'ratelimit:feeds']);
    $routes->get('public/(:segment)', 'IcalFeed::public/$1', ['filter' => 'ratelimit:feeds']);
});
