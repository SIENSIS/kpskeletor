<?php

declare(strict_types=1);

namespace Themes\Default;

use Modules\Core\Theme\ThemeManifest;

/**
 * The default theme: Bootstrap 5.3 (served locally from public/assets/vendor), every colour a
 * CSS custom property so options and child themes only change variables.
 */
final class Theme extends ThemeManifest
{
    public string $name    = 'Default';
    public string $version = '1.0.0';
    public string $author  = 'SIENSIS';

    public array $layouts = ['backend', 'public', 'auth', 'blank', 'print'];

    public array $regions = [
        'backend' => ['sidebar', 'topbar', 'footer'],
        'public'  => ['header', 'sidebar', 'footer', 'home'],
        'auth'    => ['footer'],
    ];

    public array $menuLocations = [
        'primary' => 'ThemeDefault.menu.primary',
        'footer'  => 'ThemeDefault.menu.footer',
        'social'  => 'ThemeDefault.menu.social',
    ];

    public array $styles = [
        'vendor:bootstrap/css/bootstrap.min.css',
        'vendor:bootstrap-icons/font/bootstrap-icons.min.css',
        'css/theme.css',
    ];

    public array $scripts = [
        'vendor:bootstrap/js/bootstrap.bundle.min.js',
    ];

    public array $options = [
        'logo' => [
            'type'    => 'string',
            'default' => '',
            'label'   => 'ThemeDefault.options.logo',
            'help'    => 'ThemeDefault.options.logoHelp',
        ],
        'primaryColor' => [
            'type'    => 'color',
            'default' => '#0d6efd',
            'label'   => 'ThemeDefault.options.primaryColor',
            'css'     => '--bs-primary',
        ],
        'sidebarColor' => [
            'type'    => 'color',
            'default' => '#1e293b',
            'label'   => 'ThemeDefault.options.sidebarColor',
            'css'     => '--kp-sidebar-bg',
        ],
        'font' => [
            'type'    => 'select',
            'default' => 'system',
            'label'   => 'ThemeDefault.options.font',
            'options' => [
                'system' => 'ThemeDefault.options.fontSystem',
                'serif'  => 'ThemeDefault.options.fontSerif',
                'mono'   => 'ThemeDefault.options.fontMono',
            ],
        ],
        'footerText' => [
            'type'    => 'text',
            'default' => '',
            'label'   => 'ThemeDefault.options.footerText',
        ],
        'cookieNotice' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'ThemeDefault.options.cookieNotice',
        ],
    ];
}
