<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Security headers of every response, sent by the Core `securityheaders` filter
 * (Modules\Core\Filters\SecurityHeaders), which replaces CodeIgniter's `secureheaders` filter
 * (decision D-00-28: it hard-codes other values; never use both). See docs/security.md.
 *
 * Every scalar can be overridden from `.env` (e.g. `securityHeaders.frameOptions = SAMEORIGIN`).
 * A header a controller already set on its response is left as it is.
 */
class SecurityHeaders extends BaseConfig
{
    /**
     * Strict-Transport-Security: null = only in production (the default), true = always,
     * false = never (e.g. a site that cannot serve HTTPS yet; `security:check` warns).
     */
    public ?bool $hsts = null;

    /** HSTS max-age in seconds; never less than one year when sent. */
    public int $hstsMaxAge = 31536000;

    public bool $hstsIncludeSubDomains = true;

    /** Only after submitting the domain to the preload list. */
    public bool $hstsPreload = false;

    public string $referrerPolicy = 'strict-origin-when-cross-origin';

    /** X-Frame-Options for old browsers (CSP `frame-ancestors 'none'` is the modern rule). */
    public string $frameOptions = 'DENY';

    public string $contentTypeOptions = 'nosniff';

    public string $crossOriginOpenerPolicy = 'same-origin';

    /**
     * Permissions-Policy: feature => allow-list (`()` = disabled for everybody, `(self)` = this
     * site only).
     *
     * @var array<string, string>
     */
    public array $permissionsPolicy = [
        'accelerometer'   => '()',
        'autoplay'        => '()',
        'camera'          => '()',
        'display-capture' => '()',
        'geolocation'     => '()',
        'gyroscope'       => '()',
        'magnetometer'    => '()',
        'microphone'      => '()',
        'midi'            => '()',
        'payment'         => '()',
        'usb'             => '()',
    ];

    /**
     * Extra headers: name => value.
     *
     * @var array<string, string>
     */
    public array $extra = [];
}
