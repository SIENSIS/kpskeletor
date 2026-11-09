<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;
use CodeIgniter\Shield\Authentication\Authenticators\HmacSha256;
use CodeIgniter\Shield\Authentication\Passwords\CompositionValidator;
use CodeIgniter\Shield\Authentication\Passwords\DictionaryValidator;
use CodeIgniter\Shield\Authentication\Passwords\NothingPersonalValidator;
use CodeIgniter\Shield\Config\Auth as ShieldAuth;
use Modules\Auth\Authentication\SessionAuthenticator;
use Modules\Auth\Authentication\UserTwoFactorAction;

/**
 * Shield's configuration with the skeleton's defaults (docs/specification.md, "Authentication").
 *
 * The properties after Shield's are the Auth module's security settings: the module manifest
 * (`modules/Auth/Config/Module.php`) declares them for Settings › Security and the values stored
 * there are read with `setting('Auth.<name>')`. Shield reads some properties directly from
 * `config('Auth')`, so `Modules\Auth\Services\AuthPolicy::apply()` copies the stored values into
 * this shared instance early in every request (decision D-00-07).
 */
class Auth extends ShieldAuth
{
    /**
     * The login, magic-link and reset screens are the Auth module's own controllers and views;
     * Shield's action views (2FA, activation) are themed in session 06.
     *
     * @var array<string, string>
     */
    public array $views = [
        'login'                       => '\Modules\Auth\Views\login',
        'register'                    => '\Modules\Auth\Views\login',
        'layout'                      => '\CodeIgniter\Shield\Views\layout',
        'action_email_2fa'            => '\CodeIgniter\Shield\Views\email_2fa_show',
        'action_email_2fa_verify'     => '\CodeIgniter\Shield\Views\email_2fa_verify',
        'action_email_2fa_email'      => '\CodeIgniter\Shield\Views\Email\email_2fa_email',
        'action_email_activate_show'  => '\CodeIgniter\Shield\Views\email_activate_show',
        'action_email_activate_email' => '\CodeIgniter\Shield\Views\Email\email_activate_email',
        'magic-link-login'            => '\Modules\Auth\Views\magic_link',
        'magic-link-message'          => '\Modules\Auth\Views\message',
        'magic-link-email'            => '\Modules\Auth\Views\emails\magic_link',
    ];

    /**
     * A login lands on the dashboard (`backend`, Dashboard module; D-16-01).
     *
     * @var array<string, string>
     */
    public array $redirects = [
        'register'          => 'login',
        'login'             => 'backend',
        'logout'            => 'login',
        'force_reset'       => 'backend/profile/password',
        'permission_denied' => 'login',
        'group_denied'      => 'login',
    ];

    /** Accounts are created by administrators (Users module). */
    /**
     * Shield has one global `login` action: the Auth module's `UserTwoFactorAction` dispatches to
     * the email code, the authenticator app (TOTP) or nothing according to each user's method
     * (decision D-00-13).
     *
     * @var array<string, class-string<\CodeIgniter\Shield\Authentication\Actions\ActionInterface>|null>
     */
    public array $actions = [
        'register' => null,
        'login'    => UserTwoFactorAction::class,
    ];

    /**
     * Shield's authenticators; `session` is the Auth module's subclass that issues remember-me
     * only after the 2FA step (decision D-06-03).
     *
     * @var array<string, class-string<\CodeIgniter\Shield\Authentication\AuthenticatorInterface>>
     */
    public array $authenticators = [
        'tokens'  => AccessTokens::class,
        'session' => SessionAuthenticator::class,
        'hmac'    => HmacSha256::class,
    ];

    public bool $allowRegistration = false;

    public bool $allowMagicLinkLogins = true;

    /** Lifetime of magic links and password-reset links, in seconds. */
    public int $magicLinkLifetime = HOUR;

    /**
     * `rememberLength` is overwritten with `rememberLifetime` (days) by AuthPolicy.
     *
     * @var array<string, bool|int|string>
     */
    public array $sessionConfig = [
        'field'              => 'user',
        'allowRemembering'   => true,
        'rememberCookieName' => 'remember',
        'rememberLength'     => 14 * DAY,
    ];

    /** Shield's name (decision D-00-07); 12 is recommended. */
    public int $minimumPasswordLength = 8;

    /**
     * Local validators only: never Shield's PwnedValidator (an outbound call).
     *
     * @var list<class-string<\CodeIgniter\Shield\Authentication\Passwords\ValidatorInterface>>
     */
    public array $passwordValidators = [
        CompositionValidator::class,
        NothingPersonalValidator::class,
        DictionaryValidator::class,
    ];

    /** @var list<string> */
    public array $validFields = ['email'];

    // ------------------------------------------------------------------
    // Auth module settings (declared in modules/Auth/Config/Module.php)
    // ------------------------------------------------------------------

    /** Absolute session lifetime in minutes: the session ends this long after the login. */
    public int $absoluteLifetime = 720;

    /** Idle lock in minutes (0 = off): the session asks for the password again. */
    public int $idleTimeout = 0;

    /** Remember-me lifetime in days (0 = remember-me off); it never extends itself. */
    public int $rememberLifetime = 14;

    /** Local captcha on the login, magic-link and reset forms. */
    public bool $captcha = false;

    /** Email the user when the account signs in from a new device or browser. */
    public bool $newDeviceNotice = true;

    /** Failed logins allowed per IP address within `loginLockMinutes`. */
    public int $loginAttemptsPerIp = 10;

    /** Failed logins allowed per account within `loginLockMinutes`. */
    public int $loginAttemptsPerAccount = 5;

    /** Window and lock time of the login limits, in minutes. */
    public int $loginLockMinutes = 15;

    /** Magic-link and password-reset requests allowed per IP address and hour. */
    public int $emailLinksPerIp = 10;

    /** Magic-link and password-reset emails sent per account and hour (extra requests are silent). */
    public int $emailLinksPerAccount = 3;

    /**
     * Minutes a re-authentication ("sudo mode") lasts for the session.
     */
    public int $reauthMinutes = 15;
}
