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
 *
 * Session 47 (D-47-01, D-47-02): the resource `blocks` (reusable blocks, `backend/pages/blocks`):
 * `pages-blocks.{list,add,edit,delete}`; the resource `pages` keeps its own prefix (a module with
 * several resources may name one after itself). Section type `pages.block`, page models
 * `pages.school` and `pages.company` ("New page from a model", `pages.add`).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Pages';
    public string $version = '0.2.0';
    public string $icon    = 'bi bi-file-earmark-richtext';
    public array $requires = ['Core' => '^1.0', 'Auth' => '^0.2'];

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
        // Reusable blocks (D-47-01): edited once, shown in pages and in the theme's zones.
        'blocks' => [
            'label'  => 'Pages.res.blocks',
            'type'   => 'crud',
            'except' => ['show', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['pages.*', 'pages-blocks.*'],
        'editor' => ['pages.list', 'pages.show', 'pages.add', 'pages.edit', 'pages.publish', 'pages-blocks.list', 'pages-blocks.add', 'pages-blocks.edit'],
    ];

    public array $menu = [
        ['resource' => 'pages', 'label' => 'Pages.menu.pages', 'icon' => 'bi bi-file-earmark-richtext', 'order' => 25],
        ['resource' => 'blocks', 'label' => 'Pages.menu.blocks', 'icon' => 'bi bi-bricks', 'order' => 26],
    ];

    /** Link types of the public menu editor (Menus, session 21). */
    public array $menuLinks = [\Modules\Pages\Menu\PageLink::class];

    /**
     * Section types of pages built from sections (D-45-01; docs/sections.md): hero banner, quick
     * links, cards, image and text, call to action and text; session 46: carousel, testimonials,
     * logos and embedded content (D-46-01, D-46-02).
     */
    public array $sectionTypes = [
        \Modules\Pages\Sections\HeroSection::class,
        \Modules\Pages\Sections\LinksSection::class,
        \Modules\Pages\Sections\CardsSection::class,
        \Modules\Pages\Sections\MediaTextSection::class,
        \Modules\Pages\Sections\CtaSection::class,
        \Modules\Pages\Sections\TextSection::class,
        \Modules\Pages\Sections\CarouselSection::class,
        \Modules\Pages\Sections\TestimonialsSection::class,
        \Modules\Pages\Sections\LogosSection::class,
        \Modules\Pages\Sections\EmbedSection::class,
        \Modules\Pages\Sections\BlockSection::class,
    ];

    /** Ready-made page models ("New page from a model", D-47-02): generic only (D-47-01). */
    public array $pageModels = [
        \Modules\Pages\Models\SchoolModel::class,
        \Modules\Pages\Models\CompanyModel::class,
    ];

    public array $settings = [
        'homePage' => [
            'type'            => 'select',
            'default'         => '',
            'optionsProvider' => \Modules\Pages\Settings\HomePageOptions::class,
            'label'           => 'Pages.settings.homePage',
            'help'            => 'Pages.settings.homePageHelp',
            'group'           => 'public',
        ],
        // Hosts whose pages may be embedded (D-46-01): Settings › Security, empty = none. Each one
        // adds `frame-src https://<host>` to the pages that embed it, and only to those.
        'embedHosts' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'outbound_hosts',
            'label'   => 'Pages.settings.embedHosts',
            'help'    => 'Pages.settings.embedHostsHelp',
            'group'   => 'security.embeds',
        ],
    ];

    /** Featured images: files of the Storage service in the folder `modules/pages` (public disk). */
    public array $backup = ['files' => ['modules/pages']];

    /**
     * No extra Content-Security-Policy sources for every page: the editor is vendored and served
     * by the site; an embed section adds `frame-src` for its own origin to its page only.
     */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /** No API resource yet (the complete core API arrives in session 26). */
    public array $api = [];
}
