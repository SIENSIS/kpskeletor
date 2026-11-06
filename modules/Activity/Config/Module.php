<?php

declare(strict_types=1);

namespace Modules\Activity\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Activity module: the read-only screens of the activity and security log
 * (docs/specification.md, "Modules" › "Activity"; docs/activity.md). The log itself (table
 * `activity_log`, `ActivityLogger`, retention task) belongs to the Core and keeps being written
 * while this module is disabled.
 *
 * - `activity-log`: the whole log (`list`, `show`, `export`) and `network` (IP addresses and user
 *   agents; hidden from users without it).
 * - `activity-mine`: "My activity", the user's own entries (`list`, `show`), granted to every
 *   group (D-00-17).
 *
 * No `add`, `edit` or `delete`: the log is append-only (only the retention task purges it).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Activity';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-activity';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'log' => [
            'label'  => 'Activity.res.log',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'delete', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'network' => 'Activity.perm.network',
            ],
        ],
        'mine' => [
            'label'  => 'Activity.res.mine',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
    ];

    public array $defaultGrants = [
        'superadmin' => ['activity-mine.*'],
        'admin'      => ['activity-log.*', 'activity-mine.*'],
        'editor'     => ['activity-mine.*'],
        'user'       => ['activity-mine.*'],
    ];

    public array $menu = [
        ['resource' => 'log', 'label' => 'Activity.menu.log', 'icon' => 'bi bi-activity', 'order' => 84, 'zone' => 'settings'],
        ['resource' => 'mine', 'label' => 'Activity.menu.mine', 'icon' => 'bi bi-clock-history', 'order' => 18, 'zone' => 'user'],
    ];
}
