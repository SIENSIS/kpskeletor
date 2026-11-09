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
     * The user's notifications (list page, bell, preferences: `core-notifications.*`, granted to
     * every group) and the scheduled tasks screen (`schedule.list`, `schedule.show`,
     * `schedule.run` for "run now").
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
        'notifications' => [
            'label'  => 'Core.res.notifications',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
        'schedule' => [
            'label'  => 'Core.res.schedule',
            'type'   => 'crud',
            'name'   => 'schedule',
            'except' => ['add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'run' => 'Core.perm.run',
            ],
        ],
    ];

    public array $menu = [
        ['resource' => 'notifications', 'label' => 'Core.menu.notifications', 'icon' => 'bi bi-bell', 'order' => 15, 'zone' => 'user'],
        ['resource' => 'schedule', 'label' => 'Core.menu.schedule', 'icon' => 'bi bi-clock-history', 'order' => 85, 'zone' => 'settings'],
    ];

    /** @var list<string> */
    public array $tasks = [
        \Modules\Core\Tasks\SendQueuedEmails::class,
        \Modules\Core\Tasks\PurgeActivityLog::class,
    ];

    /** @var list<string> Dashboard widgets (docs/widgets.md): failing scheduled tasks, shortcuts per group */
    public array $widgets = [
        \Modules\Core\Widgets\ScheduledTasks::class,
        \Modules\Core\Widgets\QuickLinks::class,
    ];

    /** @var list<string> */
    public array $notifications = [
        \Modules\Core\Notifications\Types\ScheduledTaskFailed::class,
    ];

    /**
     * Edited in the Settings module (docs/settings.md): the "Core" tab (`settings.core`) and,
     * for the `security.*` groups, Settings › Security (`settings.security` + re-authentication).
     * Minimal PWA (web app manifest, icons, a service worker that caches nothing): off by default.
     * Rate limits are requests per minute or hour (Modules\Core\Security\RateLimiter::LIMITS).
     */
    public array $settings = [
        // Site-wide: the name shown in titles, emails and the authenticator app (empty: the
        // language line Core.app.name) and the time zone dates are shown in (stored in UTC).
        'siteName' => [
            'type'    => 'string',
            'default' => '',
            'rules'   => 'permit_empty|max_length[100]',
            'label'   => 'Core.settings.siteName',
            'help'    => 'Core.settings.siteNameHelp',
            'group'   => 'general',
        ],
        'timezone' => [
            'type'    => 'string',
            'default' => 'UTC',
            'rules'   => 'required|timezone',
            'label'   => 'Core.settings.timezone',
            'help'    => 'Core.settings.timezoneHelp',
            'group'   => 'general',
        ],
        'pwa' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Core.settings.pwa',
            'help'    => 'Core.settings.pwaHelp',
            'group'   => 'pwa',
        ],
        'pwaStart' => [
            'type'    => 'select',
            'default' => 'public',
            'label'   => 'Core.settings.pwaStart',
            'options' => [
                'public'  => 'Core.settings.pwaStartPublic',
                'backend' => 'Core.settings.pwaStartBackend',
            ],
            'group'   => 'pwa',
        ],
        'rateLimitBackend' => [
            'type'    => 'int',
            'default' => 300,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitBackend',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitApiToken' => [
            'type'    => 'int',
            'default' => 120,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitApiToken',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitApiIp' => [
            'type'    => 'int',
            'default' => 60,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitApiIp',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitFeeds' => [
            'type'    => 'int',
            'default' => 60,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitFeeds',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitUploads' => [
            'type'    => 'int',
            'default' => 100,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitUploads',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitForms' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[1000]',
            'label'   => 'Core.settings.rateLimitForms',
            'group'   => 'security.rateLimits',
        ],
        'rateLimitWidgets' => [
            'type'    => 'int',
            'default' => 120,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[10000]',
            'label'   => 'Core.settings.rateLimitWidgets',
            'group'   => 'security.rateLimits',
        ],
        // Runs the due scheduled tasks from back-office requests (at most once a minute) on hosts
        // without cron (Modules\Core\Filters\PoorMansCron).
        'poorMansCron' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Core.settings.poorMansCron',
            'help'    => 'Core.settings.poorMansCronHelp',
            'group'   => 'scheduler',
        ],
        // Upload rules of the Storage service (docs/storage.md): the global allow-list of
        // extensions and size limit (fields can only narrow them) and SVG through the sanitiser.
        'uploadTypes' => [
            'type'    => 'string',
            'default' => 'jpg,jpeg,png,gif,webp,pdf,txt,csv,odt,ods,odp,doc,docx,xls,xlsx,ppt,pptx',
            'rules'   => 'required|max_length[500]|upload_types',
            'label'   => 'Core.settings.uploadTypes',
            'help'    => 'Core.settings.uploadTypesHelp',
            'group'   => 'security.uploads',
        ],
        'uploadMaxSize' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[2048]',
            'label'   => 'Core.settings.uploadMaxSize',
            'help'    => 'Core.settings.uploadMaxSizeHelp',
            'group'   => 'security.uploads',
        ],
        'uploadAllowSvg' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Core.settings.uploadAllowSvg',
            'help'    => 'Core.settings.uploadAllowSvgHelp',
            'group'   => 'security.uploads',
        ],
        // Days the activity log keeps its entries (PurgeActivityLog, daily).
        'activityRetentionDays' => [
            'type'    => 'int',
            'default' => 365,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[3650]',
            'label'   => 'Core.settings.activityRetentionDays',
            'help'    => 'Core.settings.activityRetentionDaysHelp',
            'group'   => 'security.activity',
        ],
        // Extra outbound hosts, added to Config\Outbound and the manifests' $outboundHosts.
        'outboundHosts' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'outbound_hosts',
            'label'   => 'Core.settings.outboundHosts',
            'help'    => 'Core.settings.outboundHostsHelp',
            'group'   => 'security.outbound',
        ],
        // Extra Content-Security-Policy origins per directive, added to Config\ContentSecurityPolicy
        // and the manifests' $csp by the `cspmodules` filter (never keywords or wildcards for
        // scripts and styles: Modules\Core\Settings\SecurityRules).
        'cspScriptSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_script_sources',
            'label'   => 'Core.settings.cspScriptSrc',
            'help'    => 'Core.settings.cspStrictHelp',
            'group'   => 'security.csp',
        ],
        'cspStyleSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_script_sources',
            'label'   => 'Core.settings.cspStyleSrc',
            'help'    => 'Core.settings.cspStrictHelp',
            'group'   => 'security.csp',
        ],
        'cspImgSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_sources',
            'label'   => 'Core.settings.cspImgSrc',
            'help'    => 'Core.settings.cspHelp',
            'group'   => 'security.csp',
        ],
        'cspFontSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_sources',
            'label'   => 'Core.settings.cspFontSrc',
            'help'    => 'Core.settings.cspHelp',
            'group'   => 'security.csp',
        ],
        'cspConnectSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_sources',
            'label'   => 'Core.settings.cspConnectSrc',
            'help'    => 'Core.settings.cspHelp',
            'group'   => 'security.csp',
        ],
        'cspFrameSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_sources',
            'label'   => 'Core.settings.cspFrameSrc',
            'help'    => 'Core.settings.cspHelp',
            'group'   => 'security.csp',
        ],
        'cspMediaSrc' => [
            'type'    => 'list',
            'default' => [],
            'rules'   => 'csp_sources',
            'label'   => 'Core.settings.cspMediaSrc',
            'help'    => 'Core.settings.cspHelp',
            'group'   => 'security.csp',
        ],
    ];

    /**
     * upload, uninstall and demo stay with the superadmin (who bypasses the checks). Personal
     * screens (notifications) go to every group (D-00-17).
     */
    public array $defaultGrants = [
        'superadmin' => ['core-notifications.*'],
        'admin'      => ['modules.list', 'modules.toggle', 'modules.sync', 'core-notifications.*', 'schedule.*'],
        'editor'     => ['core-notifications.*'],
        'user'       => ['core-notifications.*'],
    ];
}
