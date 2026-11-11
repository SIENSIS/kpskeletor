<?php

declare(strict_types=1);

namespace Modules\Files\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Files module (docs/specification.md, "Modules" › "Files"; docs/files.md).
 *
 * One resource named `files` (single-resource module, D-00-18): the RBAC ceiling of the file
 * manager. Inside it, each folder's ACL (`folders`, `folder_permissions`, inherited by
 * subfolders) decides what a user can do:
 *
 * - `files.list`: open the file manager; `files.upload`, `files.edit` (rename, move, metadata),
 *   `files.delete` (trash, restore, purge), `files.folders` (create, rename and delete folders),
 *   `files.acl` (edit folder permissions where the ACL gives `manage`), `files.all` (every
 *   folder, every right: administrators), `files.private` (the shared folders of the private
 *   disk in the file manager).
 *
 * The personal space `users/{user_id}/` is shown to members of the groups in
 * `Files.personalSpaceGroups`; its quota is `Files.personalQuota` (MB). The private files rule
 * of the Storage service (`files/private/{key}`) becomes the folder ACL (Config/Registrar.php).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Files';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-folder2-open';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'files' => [
            'name'   => 'files',
            'label'  => 'Files.res.files',
            'type'   => 'crud',
            'except' => ['show', 'add', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'upload'  => 'Files.perm.upload',
                'folders' => 'Files.perm.folders',
                'acl'     => 'Files.perm.acl',
                'all'     => 'Files.perm.all',
                'private' => 'Files.perm.private',
            ],
        ],
    ];

    public array $defaultGrants = [
        'admin'  => ['files.*'],
        'editor' => ['files.list', 'files.upload', 'files.edit', 'files.delete', 'files.folders', 'files.private'],
    ];

    public array $menu = [
        ['resource' => 'files', 'label' => 'Files.menu.files', 'icon' => 'bi bi-folder2-open', 'order' => 30],
    ];

    public array $settings = [
        // Groups whose members see their personal space (`users/{user_id}/`) in the file manager.
        'personalSpaceGroups' => [
            'type'    => 'list',
            'default' => ['admin', 'editor'],
            'label'   => 'Files.settings.personalSpaceGroups',
            'help'    => 'Files.settings.personalSpaceGroupsHelp',
            'group'   => 'files',
        ],
        // Quota of each personal space, in MB (0: no limit).
        'personalQuota' => [
            'type'    => 'int',
            'default' => 100,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[102400]',
            'label'   => 'Files.settings.personalQuota',
            'help'    => 'Files.settings.personalQuotaHelp',
            'group'   => 'files',
        ],
    ];

    /** The uploaded files live on the Storage disks (backed up by the Backup module, session 27). */
    public array $backup = ['files' => ['users', 'shared']];

    /** No extra Content-Security-Policy sources (previews are same-origin). */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /** No API resource yet (the complete core API arrives in session 26). */
    public array $api = [];
}
