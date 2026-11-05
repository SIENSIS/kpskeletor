<?php

declare(strict_types=1);

namespace Modules\Core\Config;

/**
 * Manifest of the Core module: the kernel every other module depends on.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Core';
    public string $version = Version::CORE;
    public string $icon    = 'bi bi-cpu';

    /**
     * The Modules screen (session 29) and the module commands: modules.list, modules.toggle,
     * modules.sync, modules.upload, modules.uninstall and modules.demo.
     */
    public array $resources = [
        'modules' => [
            'label'  => 'Core.res.modules',
            'type'   => 'crud',
            'name'   => 'modules',
            'except' => ['show', 'add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'toggle'    => 'Core.perm.toggle',
                'sync'      => 'Core.perm.sync',
                'upload'    => 'Core.perm.upload',
                'uninstall' => 'Core.perm.uninstall',
                'demo'      => 'Core.perm.demo',
            ],
        ],
    ];

    /**
     * Minimal PWA (web app manifest, icons, a service worker that caches nothing): off by default.
     * Rate limits (requests per minute or hour, Modules\Core\Security\RateLimiter::LIMITS) and
     * the extra outbound hosts are edited in Settings › Security (session 14).
     */
    public array $settings = [
        'pwa' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Core.settings.pwa',
            'help'    => 'Core.settings.pwaHelp',
        ],
        'pwaStart' => [
            'type'    => 'select',
            'default' => 'public',
            'label'   => 'Core.settings.pwaStart',
            'options' => [
                'public'  => 'Core.settings.pwaStartPublic',
                'backend' => 'Core.settings.pwaStartBackend',
            ],
        ],
        'rateLimitBackend' => [
            'type'    => 'int',
            'default' => 300,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitBackend',
        ],
        'rateLimitApiToken' => [
            'type'    => 'int',
            'default' => 120,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitApiToken',
        ],
        'rateLimitApiIp' => [
            'type'    => 'int',
            'default' => 60,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitApiIp',
        ],
        'rateLimitFeeds' => [
            'type'    => 'int',
            'default' => 60,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitFeeds',
        ],
        'rateLimitUploads' => [
            'type'    => 'int',
            'default' => 100,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitUploads',
        ],
        'rateLimitForms' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[1000]',
            'label'   => 'Core.settings.rateLimitForms',
        ],
        'rateLimitWidgets' => [
            'type'    => 'int',
            'default' => 120,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitWidgets',
        ],
        // Extra outbound hosts (Settings › Security), added to Config\Outbound and the manifests.
        'outboundHosts' => [
            'type'    => 'json',
            'default' => [],
            'label'   => 'Core.settings.outboundHosts',
            'help'    => 'Core.settings.outboundHostsHelp',
        ],
    ];

    /** upload, uninstall and demo stay with the superadmin (who bypasses the checks). */
    public array $defaultGrants = [
        'admin' => ['modules.list', 'modules.toggle', 'modules.sync'],
    ];
}
