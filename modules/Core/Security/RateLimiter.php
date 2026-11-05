<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use Closure;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Throttle\Throttler;
use InvalidArgumentException;

/**
 * Named rate limits on top of CodeIgniter's Throttler (token bucket in the cache), with their
 * capacities in the Core settings (docs/specification.md, "Security baseline › Rate limits";
 * decision D-08-03). Login, password reset, magic links, 2FA codes and re-authentication keep
 * their own counters per IP and per account (Auth's `LoginThrottle`).
 *
 * ```php
 * $wait = (new RateLimiter())->attempt('feeds', $tokenHash);   // 0 = allowed
 * if ($wait > 0) { return RateLimit::tooManyRequests($request, $wait); }   // Modules\Core\Filters\RateLimit
 * ```
 *
 * Subjects (user ids, token hashes, IP addresses) are hashed before they reach the cache.
 */
final class RateLimiter
{
    /**
     * Limit name => setting (requests allowed per period), period in seconds, default capacity.
     *
     * @var array<string, array{setting: string, period: int, default: int}>
     */
    public const LIMITS = [
        'backend'  => ['setting' => 'Core.rateLimitBackend', 'period' => MINUTE, 'default' => 300],
        'apiToken' => ['setting' => 'Core.rateLimitApiToken', 'period' => MINUTE, 'default' => 120],
        'apiIp'    => ['setting' => 'Core.rateLimitApiIp', 'period' => MINUTE, 'default' => 60],
        'feeds'    => ['setting' => 'Core.rateLimitFeeds', 'period' => HOUR, 'default' => 60],
        'uploads'  => ['setting' => 'Core.rateLimitUploads', 'period' => HOUR, 'default' => 100],
        'forms'    => ['setting' => 'Core.rateLimitForms', 'period' => HOUR, 'default' => 10],
        'widgets'  => ['setting' => 'Core.rateLimitWidgets', 'period' => MINUTE, 'default' => 120],
    ];

    private readonly Throttler $throttler;

    /** @var Closure(string): mixed */
    private readonly Closure $setting;

    /**
     * @param (Closure(string): mixed)|null $setting setting reader (default: `setting()`)
     */
    public function __construct(?CacheInterface $cache = null, ?Closure $setting = null, ?Throttler $throttler = null)
    {
        $this->throttler = $throttler ?? new Throttler($cache ?? service('cache'));
        $this->setting   = $setting ?? static fn (string $key): mixed => setting($key);
    }

    /**
     * Counts one request of a subject against a named limit.
     *
     * @return int 0 when allowed, otherwise the seconds to wait (for Retry-After)
     */
    public function attempt(string $limit, string $subject, int $cost = 1): int
    {
        return $this->attemptCustom($limit, $subject, $this->capacity($limit), self::definition($limit)['period'], $cost);
    }

    /**
     * A limit that is not in the list (e.g. a module's own): capacity requests per period.
     *
     * @return int 0 when allowed, otherwise the seconds to wait
     */
    public function attemptCustom(string $name, string $subject, int $capacity, int $period, int $cost = 1): int
    {
        if ($capacity < 1 || $period < 1) {
            throw new InvalidArgumentException('A rate limit needs a capacity and a period of at least 1.');
        }

        if ($this->throttler->check($this->key($name, $subject), $capacity, $period, $cost)) {
            return 0;
        }

        return max(1, $this->throttler->getTokenTime());
    }

    /**
     * Forgets the counter of a subject (e.g. after a successful login).
     */
    public function reset(string $name, string $subject): void
    {
        $this->throttler->remove($this->key($name, $subject));
    }

    /**
     * Requests allowed per period: the setting, never below 1; the default when unset.
     */
    public function capacity(string $limit): int
    {
        $definition = self::definition($limit);
        $value      = ($this->setting)($definition['setting']);

        return is_numeric($value) ? max(1, (int) $value) : $definition['default'];
    }

    /**
     * @return array{setting: string, period: int, default: int}
     */
    public static function definition(string $limit): array
    {
        return self::LIMITS[$limit] ?? throw new InvalidArgumentException('Unknown rate limit "' . $limit . '".');
    }

    private function key(string $name, string $subject): string
    {
        return 'rl_' . preg_replace('/[^A-Za-z0-9_]/', '_', $name) . '_' . hash('sha256', $subject);
    }
}
