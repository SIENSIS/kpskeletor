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
                // The full date of birth in the user's record and the administrative birthdays calendar (D-25-08).
                'birthdate'   => 'Users.perm.birthdate',
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
        // The "Birthdays" calendar: who may see shared birthdays at all (whose: the groups table, D-25-08).
        'birthdays' => [
            'label' => 'Users.res.birthdays',
            'type'  => 'page',
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
            'users-users.access', 'users-users.birthdate', 'users-groups.*', 'users-matrix.*',
            'users-birthdays.view',
        ],
        'superadmin' => ['users-birthdays.view'],
        'editor'     => ['users-birthdays.view'],
        'user'       => ['users-birthdays.view'],
    ];

    public array $menu = [
        ['resource' => 'users', 'label' => 'Users.menu.users', 'icon' => 'bi bi-people', 'order' => 60, 'zone' => 'settings'],
        ['resource' => 'groups', 'label' => 'Users.menu.groups', 'icon' => 'bi bi-diagram-3', 'order' => 61, 'zone' => 'settings'],
        ['route' => 'users-matrix', 'permission' => 'users-matrix.view', 'label' => 'Users.menu.matrix', 'icon' => 'bi bi-grid-3x3', 'order' => 62, 'zone' => 'settings'],
    ];

    /** @var list<string> Dashboard widget "Users summary" (active users, online, without 2FA). */
    public array $widgets = [\Modules\Users\Widgets\UsersSummary::class];

    /** Birthday greetings (D-25-09): a daily task and its notification. */
    public array $tasks = [\Modules\Users\Tasks\SendBirthdayGreetings::class];

    public array $notifications = [\Modules\Users\Notifications\BirthdayGreeting::class];

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
        // The "Birthdays" calendar (calendar API, pull: Calendar/BirthdaysProvider.php).
        'birthdaysCalendar' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Users.settings.birthdaysCalendar',
            'help'    => 'Users.settings.birthdaysCalendarHelp',
            'group'   => 'calendar',
        ],
        // Birthday greetings (D-25-09); the groups to greet are chosen in Users › Groups.
        'birthdayGreetingSharedOnly' => [
            'type'    => 'bool',
            'default' => true,
            'label'   => 'Users.settings.birthdayGreetingSharedOnly',
            'help'    => 'Users.settings.birthdayGreetingSharedOnlyHelp',
            'group'   => 'greetings',
        ],
        'birthdayGreetingTime' => [
            'type'    => 'string',
            'default' => '08:00',
            'rules'   => 'required|exact_length[5]|valid_date[H:i]',
            'label'   => 'Users.settings.birthdayGreetingTime',
            'help'    => 'Users.settings.birthdayGreetingTimeHelp',
            'group'   => 'greetings',
        ],
        // One text per site language (type `translated`, D-25-10); default: Users.greetings.defaultMessage.
        'birthdayGreeting' => [
            'type'    => 'translated',
            'default' => 'Users.greetings.defaultMessage',
            'rules'   => 'required|max_length[300]',
            'label'   => 'Users.settings.birthdayGreeting',
            'help'    => 'Users.settings.birthdayGreetingHelp',
            'group'   => 'greetings',
        ],
    ];

    /**
     * Calendars of the birthdays providers (D-25-08): the shared one (consent + groups table) and
     * the administrative one (every date of birth, `users-users.birthdate`, hidden by default).
     */
    public array $calendars = [
        'birthdays'      => ['label' => 'Users.birthdays.calendar', 'color' => '#d63384', 'permission' => 'users-birthdays.view'],
        'birthdaysadmin' => ['label' => 'Users.birthdays.adminCalendar', 'color' => '#6f42c1', 'permission' => 'users-users.birthdate', 'hidden' => true],
    ];

    /** Events computed on the fly, never stored (docs/calendar.md, "Providers"). */
    public array $calendarProviders = [\Modules\Users\Calendar\BirthdaysProvider::class, \Modules\Users\Calendar\AdminBirthdaysProvider::class];

    /** Its only table, `group_birthday_audiences`, is backed up with the database (no files). */
    public array $backup = [];
}
