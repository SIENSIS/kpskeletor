<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Shield\Filters\SessionAuth;
use Modules\Auth\Filters\ForcePasswordReset;
use Modules\Auth\Filters\ForceTwoFactor;
use Modules\Core\Filters\RateLimit;
use Modules\Core\Filters\SecurityHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        // CodeIgniter's `secureheaders` is replaced by the Core's `securityheaders` (D-00-28).
        'securityheaders' => SecurityHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // The back-office chain (docs/specification.md, "RBAC"; decision D-07-01), in order:
        // login (Shield), a forced password change, the 2FA set-up a group policy requires.
        // Global filters already cover the rest of the chain for every request: the disabled-
        // module check (`moduleenabled`) and the session hardening with the idle lock
        // (`authsession`). Then the request rate limit per user (`RateLimit`, limit `backend`).
        // Maintenance mode and the activity hook join this list in the sessions that build them.
        // Each route adds its own `can:` filter.
        'backend' => [
            SessionAuth::class,
            ForcePasswordReset::class,
            ForceTwoFactor::class,
            RateLimit::class,
        ],
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',       // Web Page Caching
            'performance',     // Performance Metrics
            'toolbar',         // Debug Toolbar
            'securityheaders', // HSTS, Referrer-Policy, nosniff, frame options… (Config\SecurityHeaders)
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            'moduleenabled', // Routes of disabled modules answer 404 (Modules\Core\Filters\ModuleEnabled)
            'cspmodules',    // CSP sources declared by enabled modules (Modules\Core\Filters\ContentSecurity)
            'csrf' => ['except' => ['api/*']], // Every POST except the token-only API (CodeIgniter's CSRF, session based)
            'authsession',   // Session hardening: lifetimes, remember-me cap, remote logout, idle lock (Modules\Auth\Filters\AuthSession)
            // 'honeypot',
            // 'invalidchars',
        ],
        'after' => [
            // 'honeypot',
            // Never 'secureheaders' (replaced by the required `securityheaders`, D-00-28).
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        // Every back-office request runs the `backend` chain (RoutesSecurityTest checks it).
        'backend' => ['before' => ['backend', 'backend/*']],
    ];
}
