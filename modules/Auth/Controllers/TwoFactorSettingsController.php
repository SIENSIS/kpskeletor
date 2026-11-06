<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\AuthPolicy;
use Modules\Auth\Services\RecoveryCodes;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Controllers\BackendController;

/**
 * The user's own two-factor authentication (`backend/profile/two-factor`, resource
 * `auth-profile`): choose email codes or an authenticator app, turn it off (unless a group
 * requires it) and regenerate the recovery codes. Every change needs a recent
 * re-authentication (`reauth` filter on the routes).
 */
final class TwoFactorSettingsController extends BackendController
{
    /** Session key of the secret being enrolled (never stored before a valid code). */
    public const PENDING_SECRET = 'kp_auth_totp_pending';

    /** Session key of freshly generated recovery codes (removed when shown: shown once). */
    public const NEW_CODES = 'kp_auth_recovery_codes';

    public function index(): string
    {
        $user      = $this->user();
        $twoFactor = new TwoFactor();
        $newCodes  = session(self::NEW_CODES);
        session()->remove(self::NEW_CODES);

        $this->breadcrumb(lang('Auth.profile.title'), site_url('backend/profile'))->breadcrumb(lang('Auth.twoFactor.title'));

        return $this->render('Modules\Auth\Views\two_factor_settings', [
            'title'         => lang('Auth.twoFactor.title'),
            'icon'          => 'bi bi-shield-check',
            'method'        => $twoFactor->method((int) $user->id),
            'codesLeft'     => $twoFactor->recoveryCodesLeft((int) $user->id),
            'newCodes'      => is_array($newCodes) ? array_values(array_filter($newCodes, 'is_string')) : null,
            'required'      => (new AuthPolicy())->twoFactorRequiredFor($user->getGroups()),
            'totpAvailable' => $twoFactor->totpAvailable(),
            'canEdit'       => $this->can('auth-profile.edit'),
        ]);
    }

    public function enableEmail(): RedirectResponse
    {
        $codes = (new TwoFactor())->enableEmail($this->user());

        return $this->done(lang('Auth.twoFactor.enabledEmail'), $codes);
    }

    /**
     * Enrolment of an authenticator app: a new secret (kept in the session), its QR code and the
     * secret in text; a valid code saves it.
     *
     * @return RedirectResponse|string
     */
    public function app()
    {
        $user      = $this->user();
        $twoFactor = new TwoFactor();

        if (! $twoFactor->totpAvailable()) {
            return redirect()->to(site_url('backend/profile/two-factor'))->with('error', lang('Auth.twoFactor.noEncryptionKey'));
        }

        $secret = session(self::PENDING_SECRET);

        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactor->totp()->newSecret();
            session()->set(self::PENDING_SECRET, $secret);
        }

        $uri = $twoFactor->totp()->provisioningUri($secret, (string) $user->email, lang('Core.app.name'));

        $this->breadcrumb(lang('Auth.profile.title'), site_url('backend/profile'))
            ->breadcrumb(lang('Auth.twoFactor.title'), site_url('backend/profile/two-factor'))
            ->breadcrumb(lang('Auth.twoFactor.appTitle'));

        return $this->render('Modules\Auth\Views\two_factor_app', [
            'title'  => lang('Auth.twoFactor.appTitle'),
            'icon'   => 'bi bi-phone',
            'secret' => $secret,
            'qr'     => $twoFactor->totp()->qrDataUri($uri),
        ]);
    }

    public function confirmApp(): RedirectResponse
    {
        $secret = session(self::PENDING_SECRET);
        $code   = $this->request->getPost('code');

        if (! is_string($secret) || $secret === '') {
            return redirect()->to(site_url('backend/profile/two-factor/app'));
        }

        $codes = (new TwoFactor())->enableTotp($this->user(), $secret, is_string($code) ? mb_substr($code, 0, 20) : '');

        if ($codes === null) {
            return redirect()->to(site_url('backend/profile/two-factor/app'))->with('error', lang('Auth.twoFactor.wrongCode'));
        }

        session()->remove(self::PENDING_SECRET);

        return $this->done(lang('Auth.twoFactor.enabledApp'), $codes);
    }

    public function disable(): RedirectResponse
    {
        $user = $this->user();

        if ((new AuthPolicy())->twoFactorRequiredFor($user->getGroups())) {
            return redirect()->to(site_url('backend/profile/two-factor'))->with('error', lang('Auth.twoFactor.cannotDisable'));
        }

        (new TwoFactor())->disable($user);

        return redirect()->to(site_url('backend/profile/two-factor'))->with('success', lang('Auth.twoFactor.disabled'));
    }

    public function regenerateCodes(): RedirectResponse
    {
        $user = $this->user();

        if (! (new TwoFactor())->enabled((int) $user->id)) {
            return redirect()->to(site_url('backend/profile/two-factor'));
        }

        $codes = (new RecoveryCodes())->generate((int) $user->id);
        security_log('Auth', 'recovery_codes_regenerated', ['resource' => 'auth-profile']);

        return $this->done(lang('Auth.twoFactor.codesRegenerated'), $codes);
    }

    /**
     * @param list<string> $codes
     */
    private function done(string $message, array $codes): RedirectResponse
    {
        session()->set(self::NEW_CODES, $codes);

        return redirect()->to(site_url('backend/profile/two-factor'))->with('success', $message);
    }

    private function user(): User
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            // The `session` filter covers backend/*: unreachable without a user.
            throw new \RuntimeException('No logged-in user.');
        }

        return $user;
    }
}
