<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Models\UserTwoFactorModel;
use RuntimeException;
use Throwable;

/**
 * Each user's second factor: none, an emailed code (`email`) or an authenticator app (`totp`).
 *
 * - The TOTP secret is stored encrypted with CodeIgniter's Encryption service (`encryption.key`
 *   in `.env`); without a key the app method is unavailable rather than stored in clear.
 * - A TOTP code is accepted within ±1 time step and never twice: the last accepted step is
 *   stored and only a later step passes.
 * - Enabling a method creates a new set of recovery codes; disabling or an admin reset deletes
 *   them. Changes are logged and fire `twoFactorChanged` (the activity log and the notification
 *   of an admin reset hook into it in sessions 11 and 12).
 */
final class TwoFactor
{
    public const EMAIL = 'email';

    public const TOTP = 'totp';

    public const METHODS = [self::EMAIL, self::TOTP];

    private readonly UserTwoFactorModel $model;

    private readonly Totp $totp;

    public function __construct(?UserTwoFactorModel $model = null, ?Totp $totp = null)
    {
        $this->model = $model ?? model(UserTwoFactorModel::class);
        $this->totp  = $totp ?? new Totp();
    }

    /**
     * The user's method (`email`, `totp`) or null when 2FA is off.
     */
    public function method(int $userId): ?string
    {
        try {
            $method = $this->model->forUser($userId)['method'] ?? null;
        } catch (Throwable) {
            // Before installation (no table): nobody has 2FA.
            return null;
        }

        return in_array($method, self::METHODS, true) ? $method : null;
    }

    public function enabled(int $userId): bool
    {
        return $this->method($userId) !== null;
    }

    /**
     * Whether the authenticator-app method can be used (an encryption key is configured).
     */
    public function totpAvailable(): bool
    {
        return (string) config('Encryption')->key !== '';
    }

    public function totp(): Totp
    {
        return $this->totp;
    }

    /**
     * Switches the user to emailed codes; returns new recovery codes.
     *
     * @return list<string>
     */
    public function enableEmail(User $user): array
    {
        $this->store((int) $user->id, ['method' => self::EMAIL, 'totp_secret' => null, 'totp_last_step' => null]);

        return $this->changed($user, 'enabled', self::EMAIL);
    }

    /**
     * Switches the user to the authenticator app once a code proves the app has the secret;
     * returns new recovery codes, or null when the code is wrong.
     *
     * @return list<string>|null
     */
    public function enableTotp(User $user, string $secret, string $code): ?array
    {
        if (! $this->totpAvailable()) {
            throw new RuntimeException('The authenticator app needs an encryption key (encryption.key in .env).');
        }

        $step = $this->totp->match($secret, $code);

        if ($step === null) {
            return null;
        }

        $this->store((int) $user->id, [
            'method'         => self::TOTP,
            'totp_secret'    => base64_encode(service('encrypter')->encrypt($secret)),
            'totp_last_step' => $step,
        ]);

        return $this->changed($user, 'enabled', self::TOTP);
    }

    /**
     * Checks a code of the user's authenticator app: valid now (±1 step) and later than the last
     * accepted one. The step is recorded with a conditional update, so a code works only once
     * even for two simultaneous requests.
     */
    public function verifyTotp(int $userId, string $code): bool
    {
        $row = $this->model->forUser($userId);

        if ($row === null || $row['method'] !== self::TOTP) {
            return false;
        }

        $secret = $this->secret($row);
        $step   = $secret !== null ? $this->totp->match($secret, $code) : null;
        $last   = $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : -1;

        if ($step === null || $step <= $last) {
            return false;
        }

        $builder = $this->model->builder()->where('id', $row['id']);

        if ($row['totp_last_step'] === null) {
            $builder->where('totp_last_step', null);
        } else {
            $builder->where('totp_last_step', $last);
        }

        $builder->update(['totp_last_step' => $step]);

        return $builder->db()->affectedRows() === 1;
    }

    /**
     * Turns 2FA off for the user (their own choice after re-authentication).
     */
    public function disable(User $user): void
    {
        $this->model->where('user_id', $user->id)->delete();
        (new RecoveryCodes())->delete((int) $user->id);
        $this->changed($user, 'disabled', null, false);
    }

    /**
     * An administrator's reset: 2FA off and recovery codes deleted. If the user's group requires
     * 2FA, they must set it up again at the next login. Logged and announced with the
     * `twoFactorReset` event (notification: session 11; activity log: session 12).
     */
    public function reset(User $user, ?int $byUserId = null): void
    {
        $this->model->where('user_id', $user->id)->delete();
        (new RecoveryCodes())->delete((int) $user->id);
        (new EmailCodes())->forget($user);

        security_log('Auth', '2fa_reset', ['user_id' => $byUserId, 'resource' => 'users', 'record_id' => $user->id, 'summary' => (string) $user->username]);
        Events::trigger('twoFactorReset', $user, $byUserId);
    }

    /**
     * Recovery codes left (unused).
     */
    public function recoveryCodesLeft(int $userId): int
    {
        return (new RecoveryCodes())->remaining($userId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function secret(array $row): ?string
    {
        if (! is_string($row['totp_secret'] ?? null) || $row['totp_secret'] === '') {
            return null;
        }

        try {
            return service('encrypter')->decrypt((string) base64_decode($row['totp_secret'], true));
        } catch (Throwable $e) {
            log_message('error', 'The TOTP secret of user {id} cannot be decrypted: {message}', ['id' => $row['user_id'], 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function store(int $userId, array $data): void
    {
        $now  = Time::now()->format('Y-m-d H:i:s');
        $data = $data + ['updated_at' => $now];

        if ($this->model->forUser($userId) === null) {
            $this->model->insert($data + ['user_id' => $userId, 'enabled_at' => $now]);
        } else {
            $this->model->where('user_id', $userId)->set($data + ['enabled_at' => $now])->update();
        }
    }

    /**
     * @return list<string>
     */
    private function changed(User $user, string $what, ?string $method, bool $newCodes = true): array
    {
        $codes = $newCodes ? (new RecoveryCodes())->generate((int) $user->id) : [];

        security_log('Auth', '2fa_' . $what, ['user_id' => $user->id, 'resource' => 'auth-profile', 'summary' => $method ?? '']);
        Events::trigger('twoFactorChanged', $user, $method);

        return $codes;
    }
}
