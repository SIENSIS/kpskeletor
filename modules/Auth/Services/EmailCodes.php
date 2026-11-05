<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Authentication\UserTwoFactorAction;

/**
 * Six-digit codes sent by email (the `email` 2FA method): for the login step they live in the
 * pending Shield identity of `UserTwoFactorAction`, for re-authentication in the session. Only
 * the SHA-256 hash is kept, a code is valid for 10 minutes and used once. The email goes through
 * `SecurityMailer` (inline, short timeout, logged).
 */
final class EmailCodes
{
    public const LIFETIME = 10 * MINUTE;

    private const REAUTH_KEY = 'kp_auth_reauth_code';

    public static function newCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Whether a typed code matches a stored hash (spaces ignored, constant-time comparison).
     */
    public static function matches(?string $hash, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return is_string($hash) && $hash !== '' && preg_match('/^\d{6}$/', $code) === 1 && hash_equals($hash, self::hash($code));
    }

    /**
     * Sends a code to the user (`login` or `reauth`).
     */
    public function send(User $user, string $code, string $purpose): bool
    {
        $request = service('request');

        return (new SecurityMailer())->send($user, 'two-factor-' . $purpose, lang('Auth.twoFactor.emailSubject'), 'Modules\Auth\Views\emails\two_factor_code', [
            'code'      => $code,
            'minutes'   => (int) (self::LIFETIME / MINUTE),
            'ipAddress' => $request instanceof IncomingRequest ? $request->getIPAddress() : '',
            'date'      => Time::now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * A new re-authentication code for this session, sent by email.
     */
    public function issueForReauth(User $user): bool
    {
        $code = self::newCode();

        session()->set(self::REAUTH_KEY, [
            'user'    => (int) $user->id,
            'hash'    => self::hash($code),
            'expires' => Time::now()->getTimestamp() + self::LIFETIME,
        ]);

        return $this->send($user, $code, 'reauth');
    }

    /**
     * Uses this session's re-authentication code (single use, even when wrong it stays valid).
     */
    public function consumeForReauth(User $user, string $code): bool
    {
        $stored = session(self::REAUTH_KEY);

        if (! is_array($stored) || (int) ($stored['user'] ?? 0) !== (int) $user->id || (int) ($stored['expires'] ?? 0) < Time::now()->getTimestamp()) {
            return false;
        }

        if (! self::matches($stored['hash'] ?? null, $code)) {
            return false;
        }

        session()->remove(self::REAUTH_KEY);

        return true;
    }

    /**
     * Deletes the user's pending login code (admin reset).
     */
    public function forget(User $user): void
    {
        model(UserIdentityModel::class)->deleteIdentitiesByType($user, UserTwoFactorAction::TYPE);
    }
}
