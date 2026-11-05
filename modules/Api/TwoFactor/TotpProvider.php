<?php

declare(strict_types=1);

namespace Modules\Api\TwoFactor;

use CodeIgniter\Shield\Entities\User;
use Kpapy\Auth\Challenges;
use Kpapy\Auth\TwoFactor\TotpProvider as KpapyTotpProvider;
use Modules\Auth\Services\RecoveryCodes;
use Modules\Auth\Services\TwoFactor;

/**
 * App login second factor for users whose 2FA method is the authenticator app: the code is
 * checked by the Auth module (`TwoFactor::verifyTotp()`: encrypted secret, ±1 step, a step is
 * never accepted twice, also shared with the web login), or one of the user's recovery codes.
 */
final class TotpProvider extends KpapyTotpProvider
{
    public function isEnabledFor(object $user): bool
    {
        $twoFactor = new TwoFactor();

        return isset($user->id) && $twoFactor->totpAvailable() && $twoFactor->method((int) $user->id) === TwoFactor::TOTP;
    }

    public function start(object $user, array $challenge, Challenges $challenges): string
    {
        return lang('Api.login.useApp');
    }

    public function verify(object $user, array $challenge, string $code, Challenges $challenges): bool
    {
        if (! $user instanceof User || ! $this->isEnabledFor($user)) {
            return false;
        }

        $id = (int) $user->id;

        return (new TwoFactor())->verifyTotp($id, $code) || (new RecoveryCodes())->consume($id, $code);
    }

    /**
     * Never used: the secret stays inside the Auth module.
     */
    protected function secretFor(object $user): ?string
    {
        return null;
    }
}
