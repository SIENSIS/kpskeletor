<?php

declare(strict_types=1);

namespace Config;

use Modules\Api\Auth\TokenAuthenticator;
use Modules\Api\TwoFactor\EmailCodeProvider;
use Modules\Api\TwoFactor\TotpProvider;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Security\MaskKey;
use Throwable;

/**
 * kpapy configuration of every API resource (docs/specification.md, "JSON API (kpapy)"; every
 * option and its default: vendor/siensis/kpapy/src/Config/Kpapy.php; docs/api.md).
 *
 * - Shares with kpcrudy the permission resolver (`CrudPermissions`, deny by default) and the
 *   mask key (`app.maskKey`), so a grant and a masked id mean the same in the web and the API.
 * - Rate limits, CORS origins and the lifetime of app-login tokens come from settings
 *   (`Core.rateLimitApiToken`, `Core.rateLimitApiIp`, `Api.corsOrigins`, `Api.appTokenDays`),
 *   read by the constructor; the defaults below apply when settings cannot be read.
 * - App login: the user's authenticator app, else a code by email (users with email 2FA and
 *   users without 2FA, decision D-10-02); kpapy's `web` method is not used.
 */
class Kpapy extends \Kpapy\Config\Kpapy
{
    public bool $maskIds = true;

    /** `app.maskKey`, shared with kpcrudy (set by the constructor). */
    public string $maskKey = '';

    public ?string $permissionResolver = CrudPermissions::class;

    public bool $denyByDefault = true;

    public bool $checkScopes = true;

    /** Shield access tokens; records the address each token is used from. */
    public string $authenticator = TokenAuthenticator::class;

    /** App-login tokens (`Api.appTokenDays`); personal tokens choose their own expiry. */
    public int $tokenLifetime = 30 * DAY;

    /**
     * Second factor of the app login, in order of preference.
     *
     * @var list<string>
     */
    public array $twoFactorMethods = ['totp', 'email'];

    /**
     * @var array<string, class-string>
     */
    public array $twoFactorProviders = [
        'totp'  => TotpProvider::class,
        'email' => EmailCodeProvider::class,
    ];

    /** Seconds a sign-in code is valid, and wrong codes before the challenge is closed. */
    public int $challengeLifetime = 600;

    public int $challengeAttempts = 5;

    /** Plain HTTP refused in production (configure `Config\App::$proxyIPs` behind a proxy). */
    public bool $requireHttps = true;

    /**
     * CORS closed by default (`Api.corsOrigins`).
     *
     * @var list<string>
     */
    public array $corsOrigins = [];

    /** Per token and minute (`Core.rateLimitApiToken`). */
    public int $rateLimit = 120;

    /** Per IP and minute on routes without a token (`Core.rateLimitApiIp`). */
    public int $rateLimitPublic = 60;

    /** Per IP and per account and minute on the app login (Auth's LoginThrottle also applies). */
    public int $rateLimitLogin = 10;

    /**
     * kpapy's upload fields bypass the Storage service, so resources never use them; the path is
     * a non-public folder nothing reads.
     */
    public string $uploadPath = WRITEPATH . 'kpapy-unused-uploads';

    public string $openApiTitle = 'kpskeletor API';

    public string $openApiVersion = '1.0.0';

    /** The documentation page lives in the back office behind `can:api.docs`. */
    public bool $docsInProduction = true;

    public ?bool $docsTryIt = true;

    public function __construct()
    {
        parent::__construct();

        $this->maskKey = MaskKey::value();

        if (! function_exists('setting')) {
            return;
        }

        try {
            $this->rateLimit       = self::positive(setting('Core.rateLimitApiToken'), $this->rateLimit);
            $this->rateLimitPublic = self::positive(setting('Core.rateLimitApiIp'), $this->rateLimitPublic);
            $this->tokenLifetime   = self::positive(setting('Api.appTokenDays'), intdiv($this->tokenLifetime, DAY)) * DAY;

            $origins = setting('Api.corsOrigins');

            if (is_array($origins)) {
                $this->corsOrigins = array_values(array_filter($origins, static fn ($o): bool => is_string($o) && preg_match('#^https?://[^/\s*]+$#', $o) === 1));
            }

            $name = lang('Core.app.name');

            if ($name !== 'Core.app.name' && trim($name) !== '') {
                $this->openApiTitle = trim($name) . ' API';
            }
        } catch (Throwable) {
            // Before installation (no settings table): the defaults above.
        }
    }

    private static function positive(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
