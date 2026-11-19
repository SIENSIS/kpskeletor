<?php

declare(strict_types=1);

namespace Modules\Devtools\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Devtools module (docs/devtools.md, D-41-01 … D-41-03): development-only tools
 * (the CRUD recipes; the code generator and the package builder of sessions 42–43).
 *
 * Its locks: routes only when `ENVIRONMENT === 'development'` (`Config/Routes.php`);
 * `$developmentOnly` (never enabled or installed outside development, disabled by a sync there,
 * an error in `security:check`); the `devtools` filter (loopback client and `Host` only); the
 * permissions `devtools.view` / `devtools.write` granted to nobody (superadmins hold every
 * permission), writes behind `reauth` and `ratelimit:forms`.
 */
final class Module extends ModuleManifest
{
    public string $name        = 'Devtools';
    public string $version     = '0.1.0';
    public string $icon        = 'bi bi-tools';
    public string $description = 'Devtools.module.description';
    public string $author      = 'kpskeletor';
    public bool $enabledByDefault = false;
    public bool $developmentOnly  = true;
    public array $requires     = ['Core' => '^1.0', 'Auth' => '^0.2'];

    /** One resource, permission prefix `devtools`: `devtools.view` (screens), `devtools.write` (writes files or runs commands). */
    public array $resources = [
        'tools' => [
            'label' => 'Devtools.res.tools',
            'type'  => 'page',
            'name'  => 'devtools',
            'extra' => ['write' => 'Devtools.perm.write'],
        ],
    ];

    /** Nobody: superadmins hold every permission (D-41-03). */
    public array $defaultGrants = [];

    public array $menu = [
        ['resource' => 'tools', 'label' => 'Devtools.menu.tools', 'icon' => 'bi bi-tools', 'order' => 99, 'zone' => 'settings', 'parent' => 'group.system'],
    ];

    /** Nothing to back up: Devtools keeps no data. */
    public array $backup = [];

    public array $api = [];

    public array $csp = [];

    public array $outboundHosts = [];
}
