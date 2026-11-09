<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Services\SessionTracker;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\EscalationGuard;

/**
 * Account operations of the Users screens, always on Shield's entities (the email and the
 * password live in `auth_identities`) and under the escalation rules (`EscalationGuard`):
 *
 * - a user manages an account only when `canManageUser()` allows it (superadmins: everybody;
 *   others: accounts that hold nothing they do not hold, never a superadmin);
 * - nobody bans, forces a reset, resets the 2FA of, or logs out everywhere their own account
 *   through these screens (the profile does that);
 * - every change is written to the activity log; bans and remote logouts end the sessions.
 */
final class UserAccounts
{
    public function __construct(private readonly EscalationGuard $guard = new EscalationGuard())
    {
    }

    public function find(int|string $id): ?User
    {
        $user = model(UserModel::class)->findById((int) $id);

        return $user instanceof User ? $user : null;
    }

    /**
     * Whether `$actor` may manage `$target` (edit, ban, force reset, reset 2FA, log out).
     */
    public function canManage(User $actor, User $target, bool $self = false): bool
    {
        if (! $self && (int) $actor->id === (int) $target->id) {
            return false;
        }

        return $this->guard->canManageUser($actor, $target);
    }

    /**
     * The email of a user (Shield's email/password identity), or ''.
     */
    public static function email(int $userId): string
    {
        $identity = model(UserIdentityModel::class)->where('user_id', $userId)->where('type', 'email_password')->first();

        return $identity === null ? '' : (string) $identity->secret;
    }

    /**
     * Emails of several users at once (list column).
     *
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    public static function emails(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = db_connect()->table(config('Auth')->tables['identities'])->select('user_id, secret')
            ->where('type', 'email_password')->whereIn('user_id', $ids)->get()->getResultArray();

        $emails = [];

        foreach ($rows as $row) {
            $emails[(int) $row['user_id']] = (string) $row['secret'];
        }

        return $emails;
    }

    /**
     * Whether another user already has this email.
     */
    public static function emailTaken(string $email, int $exceptUserId = 0): bool
    {
        $builder = db_connect()->table(config('Auth')->tables['identities'])
            ->where('type', 'email_password')->where('secret', mb_strtolower(trim($email)));

        if ($exceptUserId > 0) {
            $builder->where('user_id !=', $exceptUserId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Gives a new account (row inserted by the CRUD) its email/password identity, the default
     * group and a forced password change at the first login.
     */
    public function completeNewAccount(int $userId, string $email, string $password, User $actor): void
    {
        $users = model(UserModel::class);
        $user  = $users->findById($userId);

        if (! $user instanceof User) {
            return;
        }

        $user->email    = mb_strtolower(trim($email));
        $user->password = $password;
        $users->save($user);

        $user->addGroup((string) setting('AuthGroups.defaultGroup'));
        $user->forcePasswordReset();

        activity_log('Users', 'user_created', [
            'user_id'   => $actor->id,
            'resource'  => 'users-users',
            'record_id' => $userId,
            'summary'   => (string) $user->username,
        ]);
    }

    /**
     * Changes the email of an account (edit form).
     */
    public function changeEmail(User $target, string $email, User $actor): void
    {
        $email    = mb_strtolower(trim($email));
        $previous = (string) $target->email;

        if ($email === '' || $email === mb_strtolower($previous)) {
            return;
        }

        $target->email = $email;
        model(UserModel::class)->save($target);

        security_log('Users', 'email_changed', [
            'user_id'   => $actor->id,
            'resource'  => 'users-users',
            'record_id' => $target->id,
            'summary'   => (string) $target->username,
            'before'    => ['email' => $previous],
            'after'     => ['email' => $email],
        ]);
    }

    /**
     * Bans or unbans an account; a ban ends every session and remember-me token of the user.
     *
     * @return bool Whether the user is banned now
     */
    public function toggleBan(User $target, User $actor): bool
    {
        if ($target->isBanned()) {
            $target->unBan();
            security_log('Users', 'user_unbanned', $this->context($target, $actor));

            return false;
        }

        $target->ban();
        (new SessionTracker())->endOthers((int) $target->id, SessionTracker::END_REVOKED);
        security_log('Users', 'user_banned', $this->context($target, $actor));

        return true;
    }

    /**
     * The user must change the password at the next request (Shield's forced reset).
     */
    public function forceReset(User $target, User $actor): void
    {
        $target->forcePasswordReset();
        security_log('Users', 'reset_forced', $this->context($target, $actor));
    }

    /**
     * Turns the user's 2FA off (Auth's reset: recovery codes deleted, user notified, logged).
     */
    public function resetTwoFactor(User $target, User $actor): void
    {
        (new TwoFactor())->reset($target, (int) $actor->id);
    }

    /**
     * Ends every session and remember-me token of the user.
     *
     * @return int Sessions ended
     */
    public function logoutEverywhere(User $target, User $actor): int
    {
        $ended = (new SessionTracker())->endOthers((int) $target->id, SessionTracker::END_REVOKED);
        security_log('Users', 'logged_out_everywhere', $this->context($target, $actor) + ['changes' => ['sessions' => $ended]]);

        return $ended;
    }

    /**
     * Whether the account is a superadmin (shown with a badge; only superadmins manage it).
     */
    public static function isSuperadmin(User $user): bool
    {
        return Authorization::isSuperadmin($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(User $target, User $actor): array
    {
        return [
            'user_id'   => $actor->id,
            'resource'  => 'users-users',
            'record_id' => $target->id,
            'summary'   => (string) $target->username,
        ];
    }
}
