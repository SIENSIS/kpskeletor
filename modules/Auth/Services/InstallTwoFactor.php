<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Install\InstallPrompt;

/**
 * Offers two-factor authentication to the first superadmin while installing (listener of the
 * Core's `install.superadmin` event, so the Core does not depend on Auth).
 *
 * - `--2fa=email|totp|none`, or a question when the console is interactive (default: none);
 * - email: codes sent at each login (needs mail settings later, see docs/auth.md);
 * - totp: a new secret and its `otpauth://` URI are shown, and a code from the app must
 *   confirm it (three tries) before it is stored encrypted;
 * - the recovery codes are shown once.
 *
 * The web installer passes no prompt: the superadmin sets up 2FA from the profile (the group
 * policy `Auth.twoFactorPolicy` can require it at the first login).
 */
final class InstallTwoFactor
{
    public function __construct(private readonly TwoFactor $twoFactor = new TwoFactor())
    {
    }

    public function offer(User $user, ?InstallPrompt $prompt): ?string
    {
        if ($prompt === null) {
            return null;
        }

        $method = strtolower((string) $prompt->option('2fa'));

        if ($method === '' && $prompt->interactive()) {
            $choices = $this->twoFactor->totpAvailable() ? ['none', 'totp', 'email'] : ['none', 'email'];
            $method  = $prompt->ask(lang('Auth.install.ask'), $choices);
        }

        return match ($method) {
            TwoFactor::EMAIL => $this->email($user, $prompt),
            TwoFactor::TOTP  => $this->totp($user, $prompt),
            default          => null,
        };
    }

    private function email(User $user, InstallPrompt $prompt): string
    {
        $this->showCodes($this->twoFactor->enableEmail($user), $prompt);
        $prompt->write(lang('Auth.install.emailOn'), 'green');

        return TwoFactor::EMAIL;
    }

    private function totp(User $user, InstallPrompt $prompt): ?string
    {
        if (! $this->twoFactor->totpAvailable() || ! $prompt->interactive()) {
            $prompt->write(lang('Auth.install.totpLater'), 'yellow');

            return null;
        }

        $totp   = $this->twoFactor->totp();
        $secret = $totp->newSecret();

        $prompt->write(lang('Auth.install.totpScan'));
        $prompt->write('  ' . $totp->provisioningUri($secret, (string) $user->email, (string) setting('Core.app.name')), 'yellow');
        $prompt->write(lang('Auth.install.totpSecret', [$secret]));

        for ($try = 0; $try < 3; $try++) {
            $codes = $this->twoFactor->enableTotp($user, $secret, $prompt->ask(lang('Auth.install.totpCode')));

            if ($codes !== null) {
                $this->showCodes($codes, $prompt);
                $prompt->write(lang('Auth.install.totpOn'), 'green');

                return TwoFactor::TOTP;
            }

            $prompt->write(lang('Auth.install.totpWrong'), 'red');
        }

        $prompt->write(lang('Auth.install.totpLater'), 'yellow');

        return null;
    }

    /**
     * @param list<string> $codes
     */
    private function showCodes(array $codes, InstallPrompt $prompt): void
    {
        $prompt->write(lang('Auth.install.recovery'));

        foreach ($codes as $code) {
            $prompt->write('  ' . $code, 'yellow');
        }
    }
}
