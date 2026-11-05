<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;

/**
 * Re-authentication ("sudo mode"): sensitive actions ask again for the password and, when the
 * user has 2FA, a code; the confirmation lasts `Auth.reauthMinutes` (15) for this session.
 *
 * It lives in the PHP session only: a session re-created by remember-me, or a new login after
 * the absolute lifetime, starts without it. A password login (with its 2FA step) counts as a
 * fresh confirmation; a magic link or remember-me does not.
 */
final class Reauth
{
    public const SESSION_KEY = 'kp_auth_reauth';

    /** Internal path to return to after confirming. */
    public const RETURN_KEY = 'kp_auth_reauth_return';

    /** Set by the password login until its `login` event (the 2FA step may come in between). */
    public const PASSWORD_LOGIN_KEY = 'kp_auth_password_login';

    private readonly AuthPolicy $policy;

    public function __construct(?AuthPolicy $policy = null)
    {
        $this->policy = $policy ?? new AuthPolicy();
    }

    /**
     * Whether the user confirmed their identity in this session within the window.
     */
    public function confirmed(?User $user = null): bool
    {
        $user ??= auth('session')->user();
        $stored = session(self::SESSION_KEY);

        if (! $user instanceof User || ! is_array($stored) || (int) ($stored['user'] ?? 0) !== (int) $user->id) {
            return false;
        }

        $at = (int) ($stored['at'] ?? 0);

        return $at > 0 && $at + $this->policy->reauthWindow() > Time::now()->getTimestamp();
    }

    public function confirm(User $user): void
    {
        session()->set(self::SESSION_KEY, ['user' => (int) $user->id, 'at' => Time::now()->getTimestamp()]);
    }

    public function forget(): void
    {
        session()->remove(self::SESSION_KEY);
    }

    /**
     * Seconds left of the current confirmation (0 when there is none).
     */
    public function remaining(?User $user = null): int
    {
        if (! $this->confirmed($user)) {
            return 0;
        }

        $stored = (array) session(self::SESSION_KEY);

        return max(0, (int) $stored['at'] + $this->policy->reauthWindow() - Time::now()->getTimestamp());
    }

    /**
     * Remembers an internal path (with query) to come back to after confirming.
     */
    public function rememberReturn(string $path): void
    {
        if (self::isInternalPath($path)) {
            session()->set(self::RETURN_KEY, $path);
        }
    }

    /**
     * The remembered internal path (consumed), or null.
     */
    public function takeReturn(): ?string
    {
        $path = session(self::RETURN_KEY);
        session()->remove(self::RETURN_KEY);

        return is_string($path) && self::isInternalPath($path) ? $path : null;
    }

    public static function isInternalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }
}
