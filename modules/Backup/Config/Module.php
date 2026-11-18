<?php

declare(strict_types=1);

namespace Modules\Backup\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Backup module: encrypted backups and restores of the whole application from
 * the console and the back office (docs/specification.md, "Modules" › "Backup";
 * docs/backup-restore.md).
 *
 * One resource, named `backup` (single-resource module, D-27-01): `backup.list` (the list,
 * the detail view, verify), `backup.delete`, and the extras `backup.create`, `backup.download`
 * (re-authentication) and `backup.restore` (re-authentication; also "upload a backup file").
 * Only administrators get `list` and `create` by default: a downloaded backup holds every secret
 * of `.env`, so download, delete and restore stay with the superadmin (who bypasses the checks).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Backup';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-archive';
    public array $requires = ['Core' => '^1.0', 'Auth' => '^0.2', 'Dashboard' => '^0.1'];

    public array $resources = [
        'backups' => [
            'label'  => 'Backup.res.backups',
            'type'   => 'crud',
            'name'   => 'backup',
            'except' => ['add', 'edit', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'create'   => 'Backup.perm.create',
                'download' => 'Backup.perm.download',
                'restore'  => 'Backup.perm.restore',
            ],
        ],
    ];

    public array $defaultGrants = [
        'admin' => ['backup.list', 'backup.show', 'backup.create'],
    ];

    public array $menu = [
        ['resource' => 'backups', 'label' => 'Backup.menu.backups', 'icon' => 'bi bi-archive', 'order' => 86, 'zone' => 'settings'],
    ];

    /**
     * Scheduled backups (off by default; the password is `backup.password` in `.env`) and the
     * retention of the files (the newest backup of each of the last N days, weeks and months).
     */
    public array $settings = [
        'scheduled' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Backup.settings.scheduled',
            'help'    => 'Backup.settings.scheduledHelp',
            'group'   => 'schedule',
        ],
        'scheduledType' => [
            'type'    => 'select',
            'default' => 'full',
            'label'   => 'Backup.settings.scheduledType',
            'options' => [
                'full' => 'Backup.type.full',
                'db'   => 'Backup.type.db',
            ],
            'group'   => 'schedule',
        ],
        'keepDaily' => [
            'type'    => 'int',
            'default' => 7,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[365]',
            'label'   => 'Backup.settings.keepDaily',
            'help'    => 'Backup.settings.keepHelp',
            'group'   => 'retention',
        ],
        'keepWeekly' => [
            'type'    => 'int',
            'default' => 4,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[104]',
            'label'   => 'Backup.settings.keepWeekly',
            'group'   => 'retention',
        ],
        'keepMonthly' => [
            'type'    => 'int',
            'default' => 6,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[120]',
            'label'   => 'Backup.settings.keepMonthly',
            'group'   => 'retention',
        ],
    ];

    public array $widgets = [\Modules\Backup\Widgets\Backups::class];

    public array $tasks = [
        \Modules\Backup\Tasks\ScheduledBackup::class,
        \Modules\Backup\Tasks\PruneBackups::class,
    ];

    public array $notifications = [\Modules\Backup\Notifications\BackupResult::class];

    /** Nothing beyond its table: the backup files themselves are never backed up. */
    public array $backup = [];

    public array $csp = [];

    public array $outboundHosts = [];

    public array $api = [];
}
