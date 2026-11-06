<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Changes or resets a password and applies the consequences (docs/specification.md, "Session
 * handling"): the forced reset is cleared, every other session ends (their remember-me tokens
 * too) and the access tokens obtained by app logins are revoked; personal API tokens stay.
 * Triggers the event `passwordChanged` (user, number of sessions ended).
 */
final class PasswordChanger
{
    /**
     * Marker in `auth_identities.secret2` of a personal API token (created in the back office;
     * access tokens do not use `secret2`, and `extra` holds their scopes); every other access
     * token was obtained by an app login (decision D-05-05).
     */
    public const PERSONAL_TOKEN = 'personal';

    private readonly SessionTracker $tracker;

    public function __construct(?SessionTracker $tracker = null)
    {
        $this->tracker = $tracker ?? new SessionTracker();
    }

    /**
     * @return array{sessions: int, tokens: int} Sessions ended and app tokens revoked
     */
    public function change(User $user, string $password): array
    {
        $user->setPassword($password);
        model(UserModel::class)->save($user);

        if ($user->requiresPasswordReset()) {
            $user->undoForcePasswordReset();
        }

        $sessions = $this->tracker->endOthers((int) $user->id, SessionTracker::END_PASSWORD);
        $tokens   = $this->revokeAppTokens($user);

        security_log('Auth', 'password_changed', [
            'user_id'   => auth()->id() ?? $user->id,
            'resource'  => 'auth-profile',
            'record_id' => $user->id,
            'changes'   => ['sessions_ended' => $sessions, 'app_tokens_revoked' => $tokens],
        ]);

        Events::trigger('passwordChanged', $user, $sessions);

        return ['sessions' => $sessions, 'tokens' => $tokens];
    }

    /**
     * Revokes the access tokens obtained by app logins (every token not marked personal).
     */
    public function revokeAppTokens(User $user): int
    {
        $revoked = 0;

        foreach ($user->accessTokens() as $token) {
            if (($token->secret2 ?? null) === self::PERSONAL_TOKEN) {
                continue;
            }

            $user->revokeAccessTokenBySecret((string) $token->secret);
            $revoked++;
        }

        return $revoked;
    }
}
