<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Contact module (discovered by CodeIgniter; never queries the database). A
 * disabled module answers 404 (the global `moduleenabled` filter, controller namespace).
 *
 * Back office (`backend` chain + `can:`): the CRUDs `contact-messages` and `contact-recipients`.
 * Public site (with the language prefix of `LocaleUrls`): `contact` (the form; POST with CSRF and
 * `ratelimit:forms`) and `contact/captcha` (the captcha image).
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'contact-messages', backend_path('contact/messages'), '\Modules\Contact\Controllers\Messages::index');
CrudFactory::routes($routes, 'contact-recipients', backend_path('contact/recipients'), '\Modules\Contact\Controllers\Recipients::index');

$routes->group('contact', ['namespace' => 'Modules\Contact\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ContactPage::show', ['as' => 'contact']);
    $routes->post('/', 'ContactPage::send', ['filter' => 'ratelimit:forms']);
    $routes->get('captcha', 'ContactPage::captcha');
});
