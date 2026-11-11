<?php

declare(strict_types=1);

namespace Modules\Menus\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Menus module (docs/specification.md, "Modules" › "Menus"; docs/menus.md).
 *
 * Two resources, two screens:
 * - `public` (crud − show, export, import, trash, restore, purge): the public menus, placed in
 *   the theme's locations, and their items (tree editor): `menus-public.{list,add,edit,delete}`;
 * - `backend` (page + extras): the customisation layer of the generated back-office menu:
 *   `menus-backend.view` (see the editor), `.customise` (retouch and relocate entries, custom
 *   groups), `.links` (custom links), `.reset` (reset entries, the whole menu, orphaned
 *   customisations).
 *
 * The back-office layer is applied through the Core's `backendMenu` event, public menus through
 * `publicMenu` (`menu('<location>')`) (Config/Events.php).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Menus';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-menu-button-wide';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'public' => [
            'label'  => 'Menus.res.public',
            'type'   => 'crud',
            'except' => ['show', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
        'backend' => [
            'label' => 'Menus.res.backend',
            'type'  => 'page',
            'extra' => [
                'customise' => 'Menus.perm.customise',
                'links'     => 'Menus.perm.links',
                'reset'     => 'Menus.perm.reset',
            ],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['menus-public.*', 'menus-backend.*'],
        'editor' => ['menus-public.list', 'menus-public.edit'],
    ];

    public array $menu = [
        ['resource' => 'public', 'label' => 'Menus.menu.public', 'icon' => 'bi bi-menu-button-wide', 'order' => 28],
        ['resource' => 'backend', 'label' => 'Menus.menu.backend', 'icon' => 'bi bi-layout-sidebar', 'order' => 74, 'zone' => 'settings'],
    ];

    /** Menus, items and customisations are database rows: backed up with the database. */
    public array $backup = [];

    /** No extra Content-Security-Policy sources. */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /** No API resource (the complete core API arrives in session 26). */
    public array $api = [];
}
