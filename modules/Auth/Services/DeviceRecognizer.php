<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Models\KnownDeviceModel;

/**
 * Recognises the devices and browsers a user signs in from with a random, long-lived device
 * cookie (only its SHA-256 hash is stored, per user). A login from an unknown device emails a
 * notice with a link to "my sessions" — except the very first device of an account.
 */
final class DeviceRecognizer
{
    public const COOKIE = 'kp_device';

    /** Lifetime of the device cookie: one year. */
    public const LIFETIME = YEAR;

    private readonly KnownDeviceModel $model;

    private readonly AuthPolicy $policy;

    public function __construct(?KnownDeviceModel $model = null, ?AuthPolicy $policy = null, private readonly ?SecurityMailer $mailer = null)
    {
        $this->model  = $model ?? model(KnownDeviceModel::class);
        $this->policy = $policy ?? new AuthPolicy();
    }

    /**
     * Records the device of a login. Returns true when it was new and a notice was sent.
     */
    public function loggedIn(User $user): bool
    {
        $request = service('request');
        $value   = $request instanceof IncomingRequest ? $request->getCookie((string) config('Cookie')->prefix . self::COOKIE) : null;

        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            $value = bin2hex(random_bytes(32));
        }

        $this->setCookie($value);

        $hash   = hash('sha256', $value);
        $now    = Time::now()->format('Y-m-d H:i:s');
        $known  = $this->model->where('user_id', $user->id)->where('device_hash', $hash)->first();
        $agent  = $request instanceof IncomingRequest ? mb_substr((string) $request->getUserAgent(), 0, 255) : null;
        $ip     = $request instanceof IncomingRequest ? $request->getIPAddress() : null;

        if (is_array($known)) {
            $this->model->update($known['id'], ['last_seen_at' => $now, 'ip_address' => $ip]);

            return false;
        }

        $first = $this->model->where('user_id', $user->id)->countAllResults() === 0;

        $this->model->insert([
            'user_id'       => $user->id,
            'device_hash'   => $hash,
            'user_agent'    => $agent,
            'ip_address'    => $ip,
            'first_seen_at' => $now,
            'last_seen_at'  => $now,
        ]);

        if ($first || ! $this->policy->newDeviceNotice()) {
            return false;
        }

        return ($this->mailer ?? new SecurityMailer())->send($user, 'new-device', lang('Auth.mail.newDeviceSubject'), 'Modules\Auth\Views\emails\new_device', [
            'device'    => DeviceName::fromUserAgent($agent),
            'ipAddress' => $ip,
            'date'      => $now,
            'url'       => site_url('backend/profile/sessions'),
        ]);
    }

    private function setCookie(string $value): void
    {
        service('response')->setCookie(
            self::COOKIE,
            $value,
            self::LIFETIME,
            (string) config('Cookie')->domain,
            (string) config('Cookie')->path,
            (string) config('Cookie')->prefix,
            (bool) config('Cookie')->secure,
            true,
            'Lax',
        );
    }
}
