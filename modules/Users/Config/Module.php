<?php

declare(strict_types=1);

namespace Modules\Users\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Users module: user and group administration with the atomic permission matrix
 * (docs/specification.md, "Modules" › "Users", "RBAC"; docs/users.md). Core module (cannot be
 * disabled).
 *
 * - `users-users` (kpcrudy over Shield's `users`; the email lives in `auth_identities`): list,
 *   details, add and edit, and the row operations `ban` (ban/unban), `forcereset` (force a
 *   password change), `reset2fa` (re-authentication), `logout` (log out everywhere),
 *   `impersonate` (superadmins only, re-authentication) and `access` (groups and individual
 *   permissions, re-authentication). No deletion: accounts are banned (D-15-03).
 * - `users-groups` (kpcrudy over the Core `groups` table): title, description and 2FA policy;
 *   every change needs a re-authentication; reserved groups cannot be deleted.
 * - `users-matrix` (hand-written): `view` and `edit` (atomic save, re-authentication).
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Users';
    public string $version = '0.1.0';
    public string $icon    = 'bi bi-people';
    public array $requires = ['Core' => '^0.1', 'Auth' => '^0.2'];

    public array $resources = [
        'users' => [
            'label'  => 'Users.res.users',
            'type'   => 'crud',
            'except' => ['delete', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'ban'         => 'Users.perm.ban',
                'forcereset'  => 'Users.perm.forcereset',
                'reset2fa'    => 'Users.perm.reset2fa',
                'logout'      => 'Users.perm.logout',
                'impersonate' => 'Users.perm.impersonate',
                'access'      => 'Users.perm.access',
            ],
        ],
        'groups' => [
            'label'  => 'Users.res.groups',
            'type'   => 'crud',
            'except' => ['export', 'import', 'trash', 'restore', 'purge'],
        ],
        'matrix' => [
            'label' => 'Users.res.matrix',
            'type'  => 'page',
            'extra' => ['edit' => 'Users.perm.matrixEdit'],
        ],
    ];

    /**
     * Administrators manage users, groups and grants (always within what they hold themselves:
     * `EscalationGuard`); impersonation stays with the superadmin, who bypasses every check.
     */
    public array $defaultGrants = [
        'admin' => [
            'users-users.list', 'users-users.show', 'users-users.add', 'users-users.edit',
            'users-users.ban', 'users-users.forcereset', 'users-users.reset2fa', 'users-users.logout',
            'users-users.access', 'users-groups.*', 'users-matrix.*',
        ],
    ];

    public array $menu = [
        ['resource' => 'users', 'label' => 'Users.menu.users', 'icon' => 'bi bi-people', 'order' => 60, 'zone' => 'settings'],
        ['resource' => 'groups', 'label' => 'Users.menu.groups', 'icon' => 'bi bi-diagram-3', 'order' => 61, 'zone' => 'settings'],
        ['route' => 'users-matrix', 'permission' => 'users-matrix.view', 'label' => 'Users.menu.matrix', 'icon' => 'bi bi-grid-3x3', 'order' => 62, 'zone' => 'settings'],
    ];

    /** Edited in Settings › Security. */
    public array $settings = [
        // Minutes an impersonation lasts before the superadmin gets their own account back.
        'impersonationMinutes' => [
            'type'    => 'int',
            'default' => 30,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[120]',
            'label'   => 'Users.settings.impersonationMinutes',
            'help'    => 'Users.settings.impersonationMinutesHelp',
            'group'   => 'security.impersonation',
        ],
    ];

    /** No tables of its own (Shield's tables and the Core `groups` table are backed up by the Core). */
    public array $backup = [];
}
