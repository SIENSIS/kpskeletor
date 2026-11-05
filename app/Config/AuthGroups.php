<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

/**
 * The four groups of the skeleton (docs/specification.md, "Groups").
 *
 * `superadmin` bypasses RBAC, cannot be renamed or deleted and only a superadmin can assign it
 * (`Modules\Auth\Services\GroupRules`). The permissions and the matrix below are an interim
 * bootstrap until the RBAC session (07) computes them from the permission catalogue and
 * `group_permissions` and stores them with `setting('AuthGroups.…')` (decision D-05-03).
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
    public array $permissions = [
        'auth-profile.view'    => 'Auth: view own profile',
        'auth-profile.edit'    => 'Auth: edit own profile and password',
        'auth-sessions.view'   => 'Auth: view own sessions and tokens',
        'auth-sessions.revoke' => 'Auth: log out own sessions and revoke own tokens',
    ];

    /**
     * Personal screens are granted to every group (decision D-00-17).
     *
     * @var array<string, list<string>>
     */
    public array $matrix = [
        'superadmin' => ['auth-profile.*', 'auth-sessions.*'],
        'admin'      => ['auth-profile.*', 'auth-sessions.*'],
        'editor'     => ['auth-profile.*', 'auth-sessions.*'],
        'user'       => ['auth-profile.*', 'auth-sessions.*'],
    ];
}
