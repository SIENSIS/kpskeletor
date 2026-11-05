<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

/**
 * The four groups of the skeleton (docs/specification.md, "Groups").
 *
 * `superadmin` bypasses RBAC, cannot be renamed or deleted and only a superadmin can assign it
 * (`Modules\Core\Permissions\EscalationGuard`). The groups become data in session 15.
 *
 * `$permissions` and `$matrix` are empty on purpose (default deny): Shield reads the real ones
 * with `setting('AuthGroups.…')`, written by `Modules\Core\Permissions\ShieldMatrix` from the
 * permission catalogue and `group_permissions` after every `modules:sync`, module toggle and
 * grant change (decision D-00-19). Never add permissions here.
 */
class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'user';

    /**
     * @var array<string, array<string, string>>
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Superadmin',
            'description' => 'Complete control of the site.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Day-to-day administration of the site.',
        ],
        'editor' => [
            'title'       => 'Editor',
            'description' => 'Writes and publishes content.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'Registered users of the site.',
        ],
    ];

    /**
     * @var array<string, string>
     */
    public array $permissions = [];

    /**
     * @var array<string, list<string>>
     */
    public array $matrix = [];
}
