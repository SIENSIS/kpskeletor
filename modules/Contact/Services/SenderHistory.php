<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use Modules\Core\Security\RateLimiter;

/**
 * Who has been sending messages (spam layers 3 and 4), kept only in the cache and only as hashes
 * of the IP address and the email address, for one hour:
 *
 * - `allow()`: the rate limit, `Contact.rateLimit` messages per hour per IP **and** per email
 *   (Core `RateLimiter`, a token bucket);
 * - `suspicious()`: in the captcha mode "only when suspicious", whether this IP or email has
 *   already sent a message or been refused within the hour (then the captcha is required).
 */
final class SenderHistory
{
    public const PERIOD = HOUR;

    public function __construct(private readonly RateLimiter $limiter = new RateLimiter())
    {
    }

    /**
     * Counts one message against the rate limits.
     *
     * @return int 0 when allowed, otherwise the seconds to wait
     */
    public function allow(string $ip, string $email): int
    {
        $capacity = max(1, min(20, (int) setting('Contact.rateLimit')));

        $wait = $this->limiter->attemptCustom('contact', 'ip:' . $ip, $capacity, self::PERIOD);

        if ($wait > 0) {
            return $wait;
        }

        return $this->limiter->attemptCustom('contact', 'email:' . mb_strtolower(trim($email)), $capacity, self::PERIOD);
    }

    /**
     * Remembers a message sent or refused from this IP (and email, when known).
     */
    public function record(string $ip, ?string $email = null): void
    {
        foreach (array_filter([self::key('ip', $ip), $email === null || $email === '' ? null : self::key('email', mb_strtolower(trim($email)))]) as $key) {
            $count = cache($key);
            cache()->save($key, (is_int($count) ? $count : 0) + 1, self::PERIOD);
        }
    }

    public function suspicious(string $ip, ?string $email = null): bool
    {
        if (is_int(cache(self::key('ip', $ip)))) {
            return true;
        }

        return $email !== null && $email !== '' && is_int(cache(self::key('email', mb_strtolower(trim($email)))));
    }

    private static function key(string $kind, string $subject): string
    {
        return 'contact_sender_' . hash('sha256', $kind . ':' . $subject);
    }
}
