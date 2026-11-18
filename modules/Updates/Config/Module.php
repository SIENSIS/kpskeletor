<?php

declare(strict_types=1);

namespace Modules\Updates\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Updates module: update notices in the back office and verified updates from
 * the console (docs/specification.md, "Modules" › "Updates"; docs/updates.md).
 *
 * One resource, named `updates` (single-resource module): `updates.view` (the page, the widget,
 * the notification) and the extra `updates.check` ("check now"). Updating is never done from
 * the browser: `php spark app:update`.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Updates';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-cloud-arrow-down';
    public array $requires = ['Core' => '^1.0', 'Dashboard' => '^0.1', 'Backup' => '^0.1'];

    public array $resources = [
        'updates' => [
            'label' => 'Updates.res.updates',
            'type'  => 'page',
            'name'  => 'updates',
            'extra' => ['check' => 'Updates.perm.check'],
        ],
    ];

    public array $defaultGrants = [
        'admin' => ['updates.view', 'updates.check'],
    ];

    public array $menu = [
        ['route' => 'updates', 'permission' => 'updates.view', 'label' => 'Updates.menu.updates', 'icon' => 'bi bi-cloud-arrow-down', 'order' => 87, 'zone' => 'settings'],
    ];

    public array $widgets = [\Modules\Updates\Widgets\SystemVersion::class];

    public array $tasks = [\Modules\Updates\Tasks\CheckUpdates::class];

    public array $notifications = [\Modules\Updates\Notifications\NewVersion::class];

    /** No tables; the check result is a file under `writable/updates` (not backed up). */
    public array $backup = [];

    public array $csp = [];

    /**
     * Release sources: GitHub (API, downloads and the hosts its downloads redirect to) and
     * gitlab.com. A self-hosted GitLab is added to the allow-list by the administrator.
     */
    public array $outboundHosts = ['api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com', 'gitlab.com'];

    public array $api = [];
}
