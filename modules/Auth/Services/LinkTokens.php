<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Models\UserIdentityModel;

/**
 * Single-use tokens sent by email (magic-link login and password reset), stored as Shield
 * identities whose secret is the SHA-256 hash of the token: the token itself is only in the
 * email. Issuing a new token replaces the previous one of the same type.
 */
final class LinkTokens
{
    public const MAGIC_LINK = 'magic-link';

    public const RESET_PASSWORD = 'reset-password';

    private readonly AuthPolicy $policy;

    public function __construct(?AuthPolicy $policy = null)
    {
        $this->policy = $policy ?? new AuthPolicy();
    }

    public function issue(User $user, string $type): string
    {
        $identities = model(UserIdentityModel::class);
        $identities->deleteIdentitiesByType($user, $type);

        $token = bin2hex(random_bytes(24));

        $identities->insert([
            'user_id' => $user->id,
            'type'    => $type,
            'secret'  => self::hash($token),
            'expires' => Time::now()->addSeconds($this->policy->linkLifetime())->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * The identity of a valid (existing, unexpired) token, without using it.
     */
    public function find(string $type, mixed $token): ?UserIdentity
    {
        if (! is_string($token) || preg_match('/^[a-f0-9]{48}$/', $token) !== 1) {
            return null;
        }

        $identity = model(UserIdentityModel::class)->getIdentityBySecret($type, self::hash($token));

        if ($identity === null || $identity->expires === null || Time::now()->isAfter($identity->expires)) {
            return null;
        }

        return $identity;
    }

    /**
     * Uses a token: returns its user id and deletes it (also when it has expired).
     */
    public function consume(string $type, mixed $token): ?int
    {
        if (! is_string($token) || preg_match('/^[a-f0-9]{48}$/', $token) !== 1) {
            return null;
        }

        $identities = model(UserIdentityModel::class);
        $identity   = $identities->getIdentityBySecret($type, self::hash($token));

        if ($identity === null) {
            return null;
        }

        $identities->delete($identity->id);

        if ($identity->expires === null || Time::now()->isAfter($identity->expires)) {
            return null;
        }

        return (int) $identity->user_id;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
