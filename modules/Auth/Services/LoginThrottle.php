<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Cache\CacheInterface;

/**
 * Rate limits of the authentication forms, kept in the cache:
 *
 * - logins: failed attempts per IP address and per account within a window (`Auth.loginLockMinutes`);
 *   when either limit is reached the form answers 429 until the window ends;
 * - magic-link and reset requests: every request counts per IP (429 when exceeded) and per
 *   account (extra emails are silently not sent, so the answer never reveals an account).
 *
 * Accounts are keyed by a hash of the lower-cased email, never the address itself.
 */
final class LoginThrottle
{
    private readonly CacheInterface $cache;

    private readonly AuthPolicy $policy;

    public function __construct(?CacheInterface $cache = null, ?AuthPolicy $policy = null)
    {
        $this->cache  = $cache ?? service('cache');
        $this->policy = $policy ?? new AuthPolicy();
    }

    /**
     * Seconds the login form stays blocked for this IP or account (0 = allowed).
     */
    public function loginBlockedFor(string $ip, string $email): int
    {
        return max(
            $this->blockedFor('login-ip', $ip, $this->policy->loginAttemptsPerIp()),
            $email === '' ? 0 : $this->blockedFor('login-account', $this->account($email), $this->policy->loginAttemptsPerAccount()),
        );
    }

    public function loginFailed(string $ip, string $email): void
    {
        $this->hit('login-ip', $ip, $this->policy->loginLockTime());

        if ($email !== '') {
            $this->hit('login-account', $this->account($email), $this->policy->loginLockTime());
        }
    }

    /**
     * A successful login clears the account's failures (not the IP's).
     */
    public function loginSucceeded(string $email): void
    {
        $this->cache->delete($this->key('login-account', $this->account($email)));
    }

    /**
     * Counts an email-link request from this IP; returns the seconds to wait when the limit
     * is exceeded (0 = allowed).
     */
    public function linkRequestFromIp(string $ip): int
    {
        $blocked = $this->blockedFor('link-ip', $ip, $this->policy->emailLinksPerIp());

        if ($blocked > 0) {
            return $blocked;
        }

        $this->hit('link-ip', $ip, HOUR);

        return 0;
    }

    /**
     * Counts an email-link request for this account; false when no more emails may be sent.
     */
    public function linkRequestForAccount(string $email): bool
    {
        $account = $this->account($email);

        if ($this->blockedFor('link-account', $account, $this->policy->emailLinksPerAccount()) > 0) {
            return false;
        }

        $this->hit('link-account', $account, HOUR);

        return true;
    }

    private function blockedFor(string $kind, string $subject, int $limit): int
    {
        $entry = $this->cache->get($this->key($kind, $subject));

        if (! is_array($entry) || (int) ($entry['count'] ?? 0) < $limit) {
            return 0;
        }

        return max(1, (int) ($entry['reset'] ?? 0) - time());
    }

    private function hit(string $kind, string $subject, int $window): void
    {
        $key   = $this->key($kind, $subject);
        $entry = $this->cache->get($key);
        $now   = time();

        if (! is_array($entry) || (int) ($entry['reset'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'reset' => $now + $window];
        }

        $entry['count'] = (int) $entry['count'] + 1;

        $this->cache->save($key, $entry, max(1, (int) $entry['reset'] - $now));
    }

    private function account(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function key(string $kind, string $subject): string
    {
        return 'auth_throttle_' . $kind . '_' . hash('sha256', $subject);
    }
}
