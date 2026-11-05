<?php

declare(strict_types=1);

namespace Modules\Api\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Api module: the core of the JSON API under `/api/v1` built with kpapy
 * (docs/specification.md, "JSON API (kpapy)"; decision D-10-01). Core module (cannot be
 * disabled).
 *
 * - App login with a second factor (`api/v1/auth/token*`), refresh and logout, `api/v1/me` and
 *   `api/v1/me/tokens`; every module adds its own resources under `api/v1/<path>`.
 * - Personal tokens in the profile (`api.tokens`; creating one needs re-authentication).
 * - Every user's tokens, listed and revoked by administrators (`api.list`, `api.show`,
 *   `api.delete`; a kpcrudy CRUD).
 * - The documentation page with "Try it" at `backend/api/docs` (`api.docs`).
 *
 * Single resource named `api` (D-00-18), so the permissions are `api.*`.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Api';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-plug';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'api' => [
            'label'  => 'Api.res.api',
            'type'   => 'crud',
            'name'   => 'api',
            'except' => ['add', 'edit', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'docs'   => 'Api.perm.docs',
                'tokens' => 'Api.perm.tokens',
            ],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['api.*'],
        'editor' => ['api.tokens'],
    ];

    public array $menu = [
        ['resource' => 'api', 'label' => 'Api.menu.tokens', 'icon' => 'bi bi-key', 'order' => 80, 'zone' => 'settings'],
        ['route' => 'api-docs', 'permission' => 'api.docs', 'label' => 'Api.menu.docs', 'icon' => 'bi bi-book', 'order' => 81, 'zone' => 'settings'],
        ['route' => 'api-personal-tokens', 'permission' => 'api.tokens', 'label' => 'Api.menu.personal', 'icon' => 'bi bi-key', 'order' => 25, 'zone' => 'user'],
    ];

    /** Edited in Settings › Security (session 14). */
    public array $settings = [
        // Also publishes the OpenAPI document at /api/v1/openapi.json (without a token).
        'publicSpec' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Api.settings.publicSpec',
            'help'    => 'Api.settings.publicSpecHelp',
        ],
        // Origins allowed to call the API from a browser (CORS); empty = none.
        'corsOrigins' => [
            'type'    => 'json',
            'default' => [],
            'label'   => 'Api.settings.corsOrigins',
            'help'    => 'Api.settings.corsOriginsHelp',
        ],
        // Days a token obtained by an app login lasts (it can be refreshed before).
        'appTokenDays' => [
            'type'    => 'int',
            'default' => 30,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[365]',
            'label'   => 'Api.settings.appTokenDays',
        ],
    ];

    /** Kpapy's own routes (`Kpapy\Auth\TokenController`) are not module handlers. */
    public array $api = [
        'me' => ['path' => 'me', 'handler' => '\Modules\Api\Controllers\Me::index'],
    ];

    /** The access tokens' last IP addresses. */
    public array $backup = [
        'tables' => ['api_token_ips'],
    ];
}
