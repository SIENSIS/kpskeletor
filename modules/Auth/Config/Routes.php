<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Auth module (discovered by CodeIgniter; never queries the database).
 *
 * Public: login, logout, magic link, password reset, captcha image and the idle lock screen.
 * Back office (every route has its `can:` filter; `session` and `forcereset` cover `backend/*`
 * in app/Config/Filters.php): the profile (`auth-profile`) and "my sessions" (`auth-sessions`).
 *
 * @var RouteCollection $routes
 */
$routes->group('', ['namespace' => 'Modules\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('login', 'LoginController::show', ['as' => 'login']);
    $routes->post('login', 'LoginController::attempt');
    $routes->get('logout', 'LoginController::confirmLogout', ['as' => 'logout']);
    $routes->post('logout', 'LoginController::logout');

    $routes->get('login/magic-link', 'MagicLinkController::show', ['as' => 'magic-link']);
    $routes->post('login/magic-link', 'MagicLinkController::send');
    $routes->get('login/verify-magic-link', 'MagicLinkController::verify', ['as' => 'verify-magic-link']);

    $routes->get('reset-password', 'ResetPasswordController::show', ['as' => 'reset-password']);
    $routes->post('reset-password', 'ResetPasswordController::send');
    $routes->get('reset-password/new', 'ResetPasswordController::form', ['as' => 'reset-password-new']);
    $routes->post('reset-password/new', 'ResetPasswordController::update');

    $routes->get('auth/captcha', 'CaptchaController::image', ['as' => 'auth-captcha']);
    $routes->get('auth/lock', 'LockController::show', ['as' => 'auth-lock']);
    $routes->post('auth/lock', 'LockController::unlock');
});

$routes->group('backend/profile', ['namespace' => 'Modules\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ProfileController::index', ['as' => 'auth-profile', 'filter' => 'can:auth-profile.view']);
    $routes->post('/', 'ProfileController::update', ['filter' => 'can:auth-profile.edit']);
    $routes->get('password', 'ProfileController::password', ['as' => 'auth-profile-password', 'filter' => 'can:auth-profile.view']);
    $routes->post('password', 'ProfileController::changePassword', ['filter' => 'can:auth-profile.edit']);

    $routes->get('sessions', 'SessionsController::index', ['as' => 'auth-sessions', 'filter' => 'can:auth-sessions.view']);
    $routes->post('sessions/revoke-others', 'SessionsController::revokeOthers', ['filter' => 'can:auth-sessions.revoke']);
    $routes->post('sessions/(:num)/revoke', 'SessionsController::revoke/$1', ['filter' => 'can:auth-sessions.revoke']);
    $routes->post('tokens/(:num)/revoke', 'SessionsController::revokeToken/$1', ['filter' => 'can:auth-sessions.revoke']);
});
