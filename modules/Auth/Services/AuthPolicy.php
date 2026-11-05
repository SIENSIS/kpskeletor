<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use Throwable;

/**
 * The Auth module's security settings (`Auth.*`, defaults in `Config\Auth`, editable in
 * Settings › Security) and their copy into Shield's shared `config('Auth')` instance, which
 * Shield reads directly (decision D-00-07).
 *
 * Values that would weaken the baseline are raised to a floor here as well as refused by the
 * Settings screen: at least 8 password characters and a 5-minute absolute lifetime.
 */
final class AuthPolicy
{
    public const MIN_PASSWORD_LENGTH = 8;

    public const MIN_ABSOLUTE_LIFETIME = 5;

    /**
     * Copies the stored settings into `config('Auth')`. Called early in every request by the
     * `authsession` filter; safe before installation (no settings table: defaults stay).
     */
    public static function apply(): void
    {
        $config = config('Auth');
        $policy = new self();

        $config->minimumPasswordLength = $policy->minimumPasswordLength();
        $config->sessionConfig         = array_replace($config->sessionConfig, [
            'allowRemembering' => $policy->rememberLifetime() > 0,
            'rememberLength'   => max(1, $policy->rememberLifetime()),
        ]);
    }

    public function minimumPasswordLength(): int
    {
        return max(self::MIN_PASSWORD_LENGTH, $this->int('minimumPasswordLength', self::MIN_PASSWORD_LENGTH));
    }

    /** Absolute session lifetime in seconds. */
    public function absoluteLifetime(): int
    {
        return max(self::MIN_ABSOLUTE_LIFETIME, $this->int('absoluteLifetime', 720)) * MINUTE;
    }

    /** Idle lock in seconds (0 = off). */
    public function idleTimeout(): int
    {
        return max(0, $this->int('idleTimeout', 0)) * MINUTE;
    }

    /** Remember-me lifetime in seconds (0 = remember-me off). */
    public function rememberLifetime(): int
    {
        return max(0, $this->int('rememberLifetime', 14)) * DAY;
    }

    public function captcha(): bool
    {
        return $this->bool('captcha', false);
    }

    public function newDeviceNotice(): bool
    {
        return $this->bool('newDeviceNotice', true);
    }

    public function loginAttemptsPerIp(): int
    {
        return max(1, $this->int('loginAttemptsPerIp', 10));
    }

    public function loginAttemptsPerAccount(): int
    {
        return max(1, $this->int('loginAttemptsPerAccount', 5));
    }

    /** Window and lock time of the login limits, in seconds. */
    public function loginLockTime(): int
    {
        return max(1, $this->int('loginLockMinutes', 15)) * MINUTE;
    }

    public function emailLinksPerIp(): int
    {
        return max(1, $this->int('emailLinksPerIp', 10));
    }

    public function emailLinksPerAccount(): int
    {
        return max(1, $this->int('emailLinksPerAccount', 3));
    }

    /** Lifetime of magic links and reset links, in seconds. */
    public function linkLifetime(): int
    {
        return max(60, $this->int('magicLinkLifetime', HOUR));
    }

    /**
     * How long a re-authentication ("sudo mode") lasts, in seconds (`Auth.reauthMinutes`).
     */
    public function reauthWindow(): int
    {
        return min(240, max(1, $this->int('reauthMinutes', 15))) * MINUTE;
    }

    /**
     * 2FA policy per group (`Auth.twoFactorPolicy`): group name => `required` | `optional`;
     * a group that is not listed is optional.
     *
     * @return array<string, string>
     */
    public function twoFactorPolicy(): array
    {
        $value = $this->read('twoFactorPolicy');

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        $policy = [];

        foreach ($value as $group => $rule) {
            if (is_string($group) && in_array($rule, ['required', 'optional'], true)) {
                $policy[$group] = $rule;
            }
        }

        return $policy;
    }

    /**
     * Whether any of these groups requires two-factor authentication.
     *
     * @param list<string> $groups
     */
    public function twoFactorRequiredFor(array $groups): bool
    {
        $policy = $this->twoFactorPolicy();

        foreach ($groups as $group) {
            if (($policy[$group] ?? 'optional') === 'required') {
                return true;
            }
        }

        return false;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->read($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = $this->read($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function read(string $key): mixed
    {
        try {
            return setting('Auth.' . $key);
        } catch (Throwable) {
            // Not installed yet (no settings table): the defaults of Config\Auth apply.
            $config = config('Auth');

            return property_exists($config, $key) ? $config->{$key} : null;
        }
    }
}
