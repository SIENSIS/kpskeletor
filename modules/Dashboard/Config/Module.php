<?php

declare(strict_types=1);

namespace Modules\Dashboard\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Dashboard module: the back-office home with the widgets of the enabled modules,
 * configured per group and arranged per user (docs/specification.md, "Modules" › "Dashboard",
 * "Dashboard widgets"; docs/widgets.md).
 *
 * One resource, `dashboard` (single-resource module: names `dashboard.*`, D-00-18, D-16-01):
 * - `dashboard.view`: the home `backend`, the user's own layout and the async widget data,
 *   granted to every group (D-00-17);
 * - `dashboard.configure`: Dashboard › Group layouts (which widgets each group has, their order,
 *   size and options).
 *
 * The widget framework (interface, registry, renderer, options) is the Core's
 * (`Modules\Core\Widgets`); this module stores the layouts and shows them.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Dashboard';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-speedometer2';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'dashboard' => [
            'label' => 'Dashboard.res.dashboard',
            'type'  => 'page',
            'name'  => 'dashboard',
            'extra' => ['configure' => 'Dashboard.perm.configure'],
        ],
    ];

    public array $defaultGrants = [
        'superadmin' => ['dashboard.view'],
        'admin'      => ['dashboard.*'],
        'editor'     => ['dashboard.view'],
        'user'       => ['dashboard.view'],
    ];

    public array $menu = [
        ['route' => 'dashboard', 'permission' => 'dashboard.view', 'label' => 'Dashboard.menu.home', 'icon' => 'bi bi-speedometer2', 'order' => 1, 'zone' => 'sidebar'],
        ['route' => 'dashboard-groups', 'permission' => 'dashboard.configure', 'label' => 'Dashboard.menu.groups', 'icon' => 'bi bi-grid-1x2', 'order' => 72, 'zone' => 'settings'],
    ];

    /** Its two tables are backed up with the database; no files. */
    public array $backup = [];
}
