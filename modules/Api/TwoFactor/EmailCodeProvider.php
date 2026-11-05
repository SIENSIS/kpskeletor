<?php

declare(strict_types=1);

namespace Modules\Api\TwoFactor;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Kpapy\Auth\Challenges;
use Kpapy\Auth\TwoFactor\TwoFactorProvider;
use Modules\Auth\Services\RecoveryCodes;
use Modules\Auth\Services\SecurityMailer;
use Modules\Auth\Services\TwoFactor;

/**
 * App login second factor by email (decision D-10-02): for users whose 2FA method is the
 * emailed code and for users without 2FA. A 6-digit code, single use, valid
 * `Config\Kpapy::$challengeLifetime` seconds, `$challengeAttempts` wrong codes close the
 * challenge (kpapy's `Challenges`, only an HMAC of the code is stored).
 *
 * The email (Auth's `SecurityMailer`) names the device, the IP address and the time, and links
 * to a page where the user denies the sign-in (`auth/api-login/<challenge>`). Users with 2FA
 * can also answer with a recovery code.
 */
final class EmailCodeProvider implements TwoFactorProvider
{
    public function isEnabledFor(object $user): bool
    {
        return $user instanceof User && (string) $user->email !== '';
    }

    public function start(object $user, array $challenge, Challenges $challenges): string
    {
        $code    = $challenges->issueCode((string) $challenge['id'], false);
        $minutes = max(1, (int) ceil((strtotime((string) $challenge['expires_at']) - time()) / 60));

        if ($user instanceof User) {
            (new SecurityMailer())->send($user, 'api-login-code', lang('Api.login.emailSubject'), 'Modules\Api\Views\emails\login_code', [
                'code'      => $code,
                'minutes'   => $minutes,
                'device'    => (string) $challenge['device'],
                'ipAddress' => (string) $challenge['ip_address'],
                'date'      => Time::now()->format('Y-m-d H:i:s'),
                'denyUrl'   => site_url('auth/api-login/' . $challenge['id']),
            ]);
        }

        return lang('Api.login.codeSent');
    }

    public function verify(object $user, array $challenge, string $code, Challenges $challenges): bool
    {
        if ($challenges->codeMatches($challenge, preg_replace('/\s+/', '', $code) ?? '')) {
            return true;
        }

        $id = (int) ($user->id ?? 0);

        return $id > 0 && (new TwoFactor())->enabled($id) && (new RecoveryCodes())->consume($id, $code);
    }
}
