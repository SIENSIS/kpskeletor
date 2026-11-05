<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Api module (discovered by CodeIgniter; never queries the database).
 *
 * - `api/v1/…`: token only, never cookies (excluded from CSRF in app/Config/Filters.php). The
 *   app login (kpapy's endpoints, `Modules\Api\Controllers\LoginController`) uses `kpapy:login`;
 *   the rest needs a token (`kpapy`). Modules add their own resources under `api/v1/<path>`.
 * - `auth/api-login/<challenge>`: "it was not me" link of the sign-in code email (CSRF).
 * - `backend/…`: personal tokens (`api.tokens`; creating one needs `reauth`), every user's tokens
 *   (kpcrudy, `api.list`), the documentation (`api.docs`).
 *
 * @var RouteCollection $routes
 */
$routes->group('api/v1', static function (RouteCollection $routes): void {
    $login = ['filter' => 'kpapy:login'];
    $token = ['filter' => 'kpapy'];

    $routes->post('auth/token', '\Modules\Api\Controllers\LoginController::token', $login);
    $routes->post('auth/token/verify', '\Modules\Api\Controllers\LoginController::verify', $login);
    $routes->delete('auth/token', '\Modules\Api\Controllers\LoginController::logout', $token);
    $routes->post('auth/token/refresh', '\Modules\Api\Controllers\LoginController::refresh', $token);
    $routes->get('me/tokens', '\Kpapy\Auth\TokenController::index', $token);
    $routes->delete('me/tokens/(:num)', '\Kpapy\Auth\TokenController::delete/$1', $token);
    $routes->get('me', '\Modules\Api\Controllers\Me::index', $token + ['as' => 'api-me']);

    // CORS preflights of the endpoints above (answered by the filter).
    foreach (['auth/token', 'auth/token/verify', 'auth/token/refresh', 'me', 'me/tokens', 'me/tokens/(:num)'] as $path) {
        $routes->options($path, static fn () => service('response')->setStatusCode(204), ['filter' => 'kpapy:public']);
    }

    $routes->get('openapi.json', '\Modules\Api\Controllers\Docs::publicSpec', ['filter' => 'kpapy:public']);
});

$routes->get('auth/api-login/(:segment)', '\Modules\Api\Controllers\LoginDenyController::show/$1', ['as' => 'api-login-deny']);
$routes->post('auth/api-login/(:segment)', '\Modules\Api\Controllers\LoginDenyController::deny/$1', ['filter' => 'ratelimit:forms']);

$routes->group('backend', ['namespace' => 'Modules\Api\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('profile/api-tokens', 'PersonalTokens::index', ['as' => 'api-personal-tokens', 'filter' => 'can:api.tokens']);
    $routes->post('profile/api-tokens', 'PersonalTokens::create', ['filter' => ['can:api.tokens', 'reauth']]);
    $routes->post('profile/api-tokens/(:num)/revoke', 'PersonalTokens::revoke/$1', ['filter' => 'can:api.tokens']);

    $routes->get('api/docs', 'Docs::page', ['as' => 'api-docs', 'filter' => 'can:api.docs']);
    $routes->get('api/docs/openapi.json', 'Docs::spec', ['filter' => 'can:api.docs']);
    $routes->get('api/docs/try-it.js', 'Docs::script', ['filter' => 'can:api.docs']);
});

CrudFactory::routes($routes, 'api', 'backend/api/tokens', '\Modules\Api\Controllers\Tokens::index');
