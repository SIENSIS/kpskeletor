<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Routes of the Auth module (discovered by CodeIgniter; never queries the database).
 *
 * Public: login, logout, magic link, password reset, captcha image, the idle lock screen, the
 * 2FA step of a pending login, the re-authentication screen (both check the session) and the
 * end of an impersonation.
 * Back office (every route has its `can:` filter; `session` and `forcereset` cover `backend/*`
 * in app/Config/Filters.php): the profile and its 2FA screens (`auth-profile`; changes also need
 * `reauth`) and "my sessions" (`auth-sessions`).
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

    // 2FA step of a pending login (Shield's action route names; the controller answers 404
    // unless a login is pending with UserTwoFactorAction).
    $routes->get('auth/a/show', 'TwoFactorController::show', ['as' => 'auth-action-show']);
    $routes->post('auth/a/handle', 'TwoFactorController::handle', ['as' => 'auth-action-handle']);
    $routes->post('auth/a/verify', 'TwoFactorController::verify', ['as' => 'auth-action-verify']);
    $routes->post('auth/a/cancel', 'TwoFactorController::cancel', ['as' => 'auth-action-cancel']);

    // Re-authentication ("sudo mode"); the controller requires a logged-in user.
    $routes->get('auth/reauth', 'ReauthController::show', ['as' => 'auth-reauth']);
    $routes->post('auth/reauth', 'ReauthController::confirm');
    $routes->post('auth/reauth/send-code', 'ReauthController::sendCode');

    // The way back of an impersonation (the controller requires one; CSRF).
    $routes->post('auth/impersonation/stop', 'ImpersonationController::stop', ['as' => 'auth-impersonation-stop']);

    // Avatars (private disk): signed-in users or a signed URL only (404 otherwise); the URL
    // changes with each new avatar (the file key), so it is cached for a long time.
    $routes->get('files/avatar/(:segment)', 'AvatarController::show/$1', ['as' => 'auth-avatar']);
});

$routes->group('backend/profile', ['namespace' => 'Modules\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ProfileController::index', ['as' => 'auth-profile', 'filter' => 'can:auth-profile.view']);
    $routes->post('/', 'ProfileController::update', ['filter' => 'can:auth-profile.edit']);
    $routes->get('password', 'ProfileController::password', ['as' => 'auth-profile-password', 'filter' => 'can:auth-profile.view']);
    $routes->post('password', 'ProfileController::changePassword', ['filter' => 'can:auth-profile.edit']);

    $routes->get('two-factor', 'TwoFactorSettingsController::index', ['as' => 'auth-two-factor', 'filter' => 'can:auth-profile.view']);
    $routes->post('two-factor/email', 'TwoFactorSettingsController::enableEmail', ['filter' => ['can:auth-profile.edit', 'reauth']]);
    $routes->get('two-factor/app', 'TwoFactorSettingsController::app', ['filter' => ['can:auth-profile.edit', 'reauth']]);
    $routes->post('two-factor/app', 'TwoFactorSettingsController::confirmApp', ['filter' => ['can:auth-profile.edit', 'reauth']]);
    $routes->post('two-factor/disable', 'TwoFactorSettingsController::disable', ['filter' => ['can:auth-profile.edit', 'reauth']]);
    $routes->post('two-factor/recovery-codes', 'TwoFactorSettingsController::regenerateCodes', ['filter' => ['can:auth-profile.edit', 'reauth']]);

    $routes->get('sessions', 'SessionsController::index', ['as' => 'auth-sessions', 'filter' => 'can:auth-sessions.view']);
    $routes->post('sessions/revoke-others', 'SessionsController::revokeOthers', ['filter' => 'can:auth-sessions.revoke']);
    $routes->post('sessions/(:num)/revoke', 'SessionsController::revoke/$1', ['filter' => 'can:auth-sessions.revoke']);
    $routes->post('tokens/(:num)/revoke', 'SessionsController::revokeToken/$1', ['filter' => 'can:auth-sessions.revoke']);
});
