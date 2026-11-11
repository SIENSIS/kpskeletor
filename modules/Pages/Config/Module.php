<?php

declare(strict_types=1);

namespace Modules\Pages\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Pages module: content pages of the public site (docs/specification.md,
 * "Modules" › "Pages"; docs/pages.md).
 *
 * One resource `pages` (kpcrudy), named `pages` (single-resource module, D-00-18; D-20-01):
 * permissions `pages.{list,show,add,edit,delete,export,trash,restore,purge}` plus the extra
 * `pages.publish` (publish / unpublish). The custom operations use the CRUD permissions:
 * preview and revisions `show`, restore a revision `edit`, duplicate `add`.
 *
 * No `import`: pages are written through the form (sanitised body, slugs checked per parent
 * and language, revisions).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Pages';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-file-earmark-richtext';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'pages' => [
            'name'   => 'pages',
            'label'  => 'Pages.res.pages',
            'type'   => 'crud',
            'except' => ['import'],
            'extra'  => [
                'publish' => 'Pages.perm.publish',
            ],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['pages.*'],
        'editor' => ['pages.list', 'pages.show', 'pages.add', 'pages.edit', 'pages.publish'],
    ];

    public array $menu = [
        ['resource' => 'pages', 'label' => 'Pages.menu.pages', 'icon' => 'bi bi-file-earmark-richtext', 'order' => 25],
    ];

    /** Link types of the public menu editor (Menus, session 21). */
    public array $menuLinks = [\Modules\Pages\Menu\PageLink::class];

    public array $settings = [
        'homePage' => [
            'type'            => 'select',
            'default'         => '',
            'optionsProvider' => \Modules\Pages\Settings\HomePageOptions::class,
            'label'           => 'Pages.settings.homePage',
            'help'            => 'Pages.settings.homePageHelp',
            'group'           => 'public',
        ],
    ];

    /** Featured images: files of the Storage service in the folder `modules/pages` (public disk). */
    public array $backup = ['files' => ['modules/pages']];

    /** No extra Content-Security-Policy sources: the editor is vendored and served by the site. */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /** No API resource yet (the complete core API arrives in session 26). */
    public array $api = [];
}
