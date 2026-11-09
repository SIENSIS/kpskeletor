<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use Modules\Core\Permissions\Authorization;

/**
 * Impersonation (docs/specification.md, "Modules" › "Users"): a superadmin acts as another user
 * in their own PHP session for a limited time.
 *
 * - Only a superadmin, never as another superadmin, a banned or inactive user or themselves, and
 *   never while already impersonating. The route that starts it requires a re-authentication
 *   (Users module); the time limit (`Users.impersonationMinutes`) is stored at the start.
 * - The PHP session keeps the impersonator's tracked row (`user_sessions`), so its absolute
 *   lifetime and remote logout still apply; the session id is regenerated at the start and the
 *   end, and the re-authentication window is forgotten both times.
 * - While impersonating, sensitive actions are refused: re-authentication (so nothing behind the
 *   `reauth` filter: 2FA, API tokens, the permission matrix…), the password change.
 * - The start and the end (by hand, expired, logout) are written to the activity log as
 *   security entries; the global `authsession` filter ends it when it expires.
 */
final class Impersonation
{
    public const SESSION_KEY = 'kp_impersonation';

    public const END_STOPPED = 'stopped';

    public const END_EXPIRED = 'expired';

    public const END_LOGOUT = 'logout';

    /**
     * The impersonation of this session, or null.
     *
     * @return array{actor: int, target: int, started: int, expires: int}|null
     */
    public static function state(): ?array
    {
        if (! function_exists('session')) {
            return null;
        }

        $state = session(self::SESSION_KEY);

        if (! is_array($state) || ! isset($state['actor'], $state['target'], $state['started'], $state['expires'])) {
            return null;
        }

        return [
            'actor'   => (int) $state['actor'],
            'target'  => (int) $state['target'],
            'started' => (int) $state['started'],
            'expires' => (int) $state['expires'],
        ];
    }

    public static function active(): bool
    {
        return self::state() !== null;
    }

    public static function expired(): bool
    {
        $state = self::state();

        return $state !== null && $state['expires'] <= Time::now()->getTimestamp();
    }

    /**
     * Seconds left (0 when not impersonating).
     */
    public static function remaining(): int
    {
        $state = self::state();

        return $state === null ? 0 : max(0, $state['expires'] - Time::now()->getTimestamp());
    }

    /**
     * Whether `$actor` may impersonate `$target` now.
     */
    public function allowed(User $actor, User $target): bool
    {
        return ! self::active()
            && Authorization::isSuperadmin($actor)
            && ! Authorization::isSuperadmin($target)
            && (int) $actor->id !== (int) $target->id
            && $target->active
            && ! $target->isBanned();
    }

    /**
     * Starts impersonating (the caller checked the re-authentication).
     *
     * @return bool False when it is not allowed (nothing changes)
     */
    public function start(User $actor, User $target, int $minutes): bool
    {
        if (! $this->allowed($actor, $target)) {
            security_log('Auth', 'impersonation_refused', [
                'user_id'   => $actor->id,
                'resource'  => 'users',
                'record_id' => $target->id,
                'summary'   => (string) $target->username,
            ]);

            return false;
        }

        $now     = Time::now()->getTimestamp();
        $minutes = max(1, $minutes);

        (new Reauth())->forget();
        session()->regenerate(true);
        session()->set((string) setting('Auth.sessionConfig')['field'], ['id' => (int) $target->id]);
        session()->set(self::SESSION_KEY, [
            'actor'   => (int) $actor->id,
            'target'  => (int) $target->id,
            'started' => $now,
            'expires' => $now + $minutes * MINUTE,
        ]);
        Services::resetSingle('auth');

        security_log('Auth', 'impersonation_started', [
            'user_id'   => $actor->id,
            'resource'  => 'users',
            'record_id' => $target->id,
            'summary'   => (string) $target->username,
            'changes'   => ['minutes' => $minutes],
        ]);

        return true;
    }

    /**
     * Ends the impersonation and logs the impersonator back in.
     *
     * @return User|null The impersonator (null when nothing was impersonated or they no longer exist)
     */
    public function stop(string $reason = self::END_STOPPED): ?User
    {
        $state = self::state();

        if ($state === null) {
            return null;
        }

        $actor = model(UserModel::class)->findById($state['actor']);

        (new Reauth())->forget();
        session()->remove(self::SESSION_KEY);
        session()->regenerate(true);

        if ($actor instanceof User) {
            session()->set((string) setting('Auth.sessionConfig')['field'], ['id' => (int) $actor->id]);
        } else {
            session()->remove((string) setting('Auth.sessionConfig')['field']);
        }

        Services::resetSingle('auth');

        security_log('Auth', 'impersonation_ended', [
            'user_id'   => $state['actor'],
            'resource'  => 'users',
            'record_id' => $state['target'],
            'summary'   => $reason,
            'changes'   => ['reason' => $reason, 'seconds' => Time::now()->getTimestamp() - $state['started']],
        ]);

        return $actor instanceof User ? $actor : null;
    }
}
