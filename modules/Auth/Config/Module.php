<?php

declare(strict_types=1);

namespace Modules\Auth\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Auth module: login (password and magic link), password reset, hardened
 * sessions, the profile and "my sessions" (docs/specification.md, "Authentication (Shield)").
 *
 * The settings are the security settings shown in Settings › Security; their defaults live in
 * `Config\Auth` (Shield's config class) and must stay equal to the ones declared here.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Auth';
    public string $version = '0.2.0';
    public string $icon    = 'bi bi-shield-lock';
    public array $requires = ['Core' => '^0.1'];

    /**
     * Personal screens: their own resources, granted to every group (decision D-00-17).
     */
    public array $resources = [
        'profile' => [
            'label' => 'Auth.res.profile',
            'type'  => 'page',
            'extra' => ['edit' => 'Auth.perm.edit'],
        ],
        'sessions' => [
            'label' => 'Auth.res.sessions',
            'type'  => 'page',
            'extra' => ['revoke' => 'Auth.perm.revoke'],
        ],
    ];

    /** The user menu (zone `user`): profile and "my sessions". */
    public array $menu = [
        ['resource' => 'profile', 'label' => 'Auth.menu.profile', 'icon' => 'bi bi-person-circle', 'order' => 10, 'zone' => 'user'],
        ['resource' => 'sessions', 'label' => 'Auth.menu.sessions', 'icon' => 'bi bi-laptop', 'order' => 20, 'zone' => 'user'],
    ];

    public array $defaultGrants = [
        'superadmin' => ['auth-profile.*', 'auth-sessions.*'],
        'admin'      => ['auth-profile.*', 'auth-sessions.*'],
        'editor'     => ['auth-profile.*', 'auth-sessions.*'],
        'user'       => ['auth-profile.*', 'auth-sessions.*'],
    ];

    /** @var list<string> Notification types (the user is told when an administrator resets their 2FA). */
    public array $notifications = [
        \Modules\Auth\Notifications\TwoFactorReset::class,
    ];

    public array $settings = [
        'minimumPasswordLength' => [
            'type'    => 'int',
            'default' => 8,
            'rules'   => 'required|integer|greater_than_equal_to[8]|less_than_equal_to[128]',
            'label'   => 'Auth.settings.minimumPasswordLength',
            'help'    => 'Auth.settings.minimumPasswordLengthHelp',
        ],
        'absoluteLifetime' => [
            'type'    => 'int',
            'default' => 720,
            'rules'   => 'required|integer|greater_than_equal_to[5]|less_than_equal_to[10080]',
            'label'   => 'Auth.settings.absoluteLifetime',
            'help'    => 'Auth.settings.absoluteLifetimeHelp',
        ],
        'idleTimeout' => [
            'type'    => 'int',
            'default' => 0,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[1440]',
            'label'   => 'Auth.settings.idleTimeout',
            'help'    => 'Auth.settings.idleTimeoutHelp',
        ],
        'rememberLifetime' => [
            'type'    => 'int',
            'default' => 14,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[365]',
            'label'   => 'Auth.settings.rememberLifetime',
            'help'    => 'Auth.settings.rememberLifetimeHelp',
        ],
        'captcha' => [
            'type'    => 'bool',
            'default' => false,
            'label'   => 'Auth.settings.captcha',
            'help'    => 'Auth.settings.captchaHelp',
        ],
        'newDeviceNotice' => [
            'type'    => 'bool',
            'default' => true,
            'label'   => 'Auth.settings.newDeviceNotice',
        ],
        'loginAttemptsPerIp' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[1000]',
            'label'   => 'Auth.settings.loginAttemptsPerIp',
        ],
        'loginAttemptsPerAccount' => [
            'type'    => 'int',
            'default' => 5,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
            'label'   => 'Auth.settings.loginAttemptsPerAccount',
        ],
        'loginLockMinutes' => [
            'type'    => 'int',
            'default' => 15,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[1440]',
            'label'   => 'Auth.settings.loginLockMinutes',
        ],
        'emailLinksPerIp' => [
            'type'    => 'int',
            'default' => 10,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[1000]',
            'label'   => 'Auth.settings.emailLinksPerIp',
        ],
        'emailLinksPerAccount' => [
            'type'    => 'int',
            'default' => 3,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
            'label'   => 'Auth.settings.emailLinksPerAccount',
        ],
        'reauthMinutes' => [
            'type'    => 'int',
            'default' => 15,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[240]',
            'label'   => 'Auth.settings.reauthMinutes',
            'help'    => 'Auth.settings.reauthMinutesHelp',
        ],
        // Group name => 'required' | 'optional' (missing groups are optional). Moves to the
        // `groups` table with the Groups screen (session 15).
        'twoFactorPolicy' => [
            'type'    => 'json',
            'default' => [],
            'label'   => 'Auth.settings.twoFactorPolicy',
            'help'    => 'Auth.settings.twoFactorPolicyHelp',
        ],
    ];

    /** Auth's tables are backed up by the Core's database dump. */
    public array $backup = [];
}
