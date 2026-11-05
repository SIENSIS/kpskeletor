<?php

declare(strict_types=1);

namespace Modules\Auth\Authentication;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Actions\ActionInterface;
use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Services\EmailCodes;
use Modules\Auth\Services\LoginThrottle;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\RecoveryCodes;
use Modules\Auth\Services\TwoFactor;

/**
 * Shield's `login` action (decision D-00-13): the second step of a password or magic-link login
 * for users who have 2FA, dispatching to their method.
 *
 * - `email`: a 6-digit code (Shield's `Email2FA` logic: a code identity for the pending login),
 *   but only its hash is stored and it is sent through `SecurityMailer`; "send a new code"
 *   replaces it within the per-account email limit;
 * - `totp`: a code of the authenticator app (±1 step, never reused);
 * - a recovery code works with either method (single use).
 *
 * The pending identity (`kp_2fa`) expires after 10 minutes; its secret is a random nonce that
 * `Session::checkAction()` compares to complete the login once a code has been verified here.
 * Wrong codes count as failed logins (per IP and per account); past the limit the pending
 * login ends.
 */
final class UserTwoFactorAction implements ActionInterface, ConditionalActionInterface
{
    public const TYPE = 'kp_2fa';

    private const VIEW = 'Modules\Auth\Views\two_factor';

    public function appliesTo(User $user): bool
    {
        return (new TwoFactor())->enabled((int) $user->id);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * Starts the step: a new pending identity and, for the email method, a code by email.
     */
    public function createIdentity(User $user): string
    {
        $identities = model(UserIdentityModel::class);
        $identities->deleteIdentitiesByType($user, self::TYPE);

        $nonce  = bin2hex(random_bytes(16));
        $method = (new TwoFactor())->method((int) $user->id);
        $code   = $method === TwoFactor::EMAIL ? EmailCodes::newCode() : null;

        $identities->insert([
            'user_id' => $user->id,
            'type'    => self::TYPE,
            'name'    => (string) $method,
            'secret'  => $nonce,
            'secret2' => $code !== null ? EmailCodes::hash($code) : null,
            'extra'   => lang('Auth.twoFactor.pending'),
            'expires' => Time::now()->addSeconds(EmailCodes::LIFETIME)->format('Y-m-d H:i:s'),
        ]);

        if ($code !== null) {
            (new EmailCodes())->send($user, $code, 'login');
        }

        return $nonce;
    }

    /**
     * The code form.
     */
    public function show(): string
    {
        $user = $this->pendingUser();

        if ($user === null) {
            return $this->render(['error' => lang('Auth.twoFactor.expired')]);
        }

        if ($this->identity($user) === null) {
            $this->createIdentity($user);
        }

        return $this->render(['user' => $user]);
    }

    /**
     * "Send a new code" (email method), within the per-account email limit.
     */
    public function handle(IncomingRequest $request): RedirectResponse
    {
        $user = $this->pendingUser();

        if ($user === null || (new TwoFactor())->method((int) $user->id) !== TwoFactor::EMAIL) {
            return redirect()->route('auth-action-show');
        }

        $throttle = new LoginThrottle();

        if ($throttle->linkRequestFromIp($request->getIPAddress()) > 0 || ! $throttle->linkRequestForAccount((string) $user->email)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.twoFactor.tooManyCodes'));
        }

        $this->createIdentity($user);

        return redirect()->route('auth-action-show')->with('message', lang('Auth.twoFactor.codeSent'));
    }

    /**
     * Checks the code (or a recovery code) and completes the login.
     *
     * @return RedirectResponse|ResponseInterface|string
     */
    public function verify(IncomingRequest $request)
    {
        $authenticator = $this->authenticator();
        $user          = $this->pendingUser();

        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $ip       = $request->getIPAddress();
        $email    = (string) $user->email;
        $throttle = new LoginThrottle();
        $identity = $this->identity($user);

        if ($throttle->loginBlockedFor($ip, $email) > 0) {
            return $this->abandon(lang('Auth.twoFactor.tooManyAttempts'));
        }

        if ($identity === null || $identity->expires === null || Time::now()->isAfter($identity->expires)) {
            return $this->abandon(lang('Auth.twoFactor.expired'));
        }

        $code     = $request->getPost('code');
        $recovery = $request->getPost('recovery_code');
        $code     = is_string($code) ? mb_substr(trim($code), 0, 20) : '';
        $recovery = is_string($recovery) ? mb_substr(trim($recovery), 0, 40) : '';
        $usedCode = false;

        if ($recovery !== '') {
            $valid    = (new RecoveryCodes())->consume((int) $user->id, $recovery);
            $usedCode = $valid;
        } elseif ($identity->name === TwoFactor::TOTP) {
            $valid = (new TwoFactor())->verifyTotp((int) $user->id, $code);
        } else {
            $valid = EmailCodes::matches($identity->secret2, $code);
        }

        if (! $valid) {
            $throttle->loginFailed($ip, $email);
            log_message('notice', 'Wrong two-factor code for user {id}.', ['id' => $user->id]);

            if ($throttle->loginBlockedFor($ip, $email) > 0) {
                return $this->abandon(lang('Auth.twoFactor.tooManyAttempts'));
            }

            return $this->render(['user' => $user, 'error' => lang('Auth.twoFactor.wrongCode'), 'recovery' => $recovery !== '']);
        }

        $throttle->loginSucceeded($email);

        // Completes the login (Shield's `login` event) with the identity's nonce.
        $authenticator->checkAction($identity, (string) $identity->secret);

        if (session(SessionAuthenticator::REMEMBER_AFTER_2FA) === true && $authenticator instanceof SessionAuthenticator) {
            $authenticator->rememberVerifiedUser($user);
        }

        session()->remove(SessionAuthenticator::REMEMBER_AFTER_2FA);

        $redirect = redirect()->to(config('Auth')->loginRedirect())->withCookies();

        if ($usedCode) {
            log_message('notice', 'User {id} logged in with a recovery code.', ['id' => $user->id]);
            $redirect->with('warning', lang('Auth.twoFactor.recoveryUsed', [(new RecoveryCodes())->remaining((int) $user->id)]));
        }

        return $redirect;
    }

    /**
     * Ends the pending login (cancel, expiry or too many attempts).
     */
    public function abandon(?string $message = null): RedirectResponse
    {
        $authenticator = $this->authenticator();
        $user          = $authenticator->getPendingUser();

        if ($user !== null) {
            model(UserIdentityModel::class)->deleteIdentitiesByType($user, self::TYPE);
        }

        $authenticator->logout();
        session()->remove([Reauth::PASSWORD_LOGIN_KEY, SessionAuthenticator::REMEMBER_AFTER_2FA]);

        $redirect = redirect()->to(site_url('login'))->withCookies();

        return $message !== null ? $redirect->with('error', $message) : $redirect;
    }

    private function authenticator(): Session
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        return $authenticator;
    }

    private function pendingUser(): ?User
    {
        return $this->authenticator()->getPendingUser();
    }

    private function identity(User $user): ?UserIdentity
    {
        return model(UserIdentityModel::class)->getIdentityByType($user, self::TYPE);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(array $data): string
    {
        $user   = $data['user'] ?? null;
        $method = $user instanceof User ? (new TwoFactor())->method((int) $user->id) : null;

        return theme_view(self::VIEW, $data + [
            'layout'  => theme_layout('auth'),
            'meta'    => [],
            'title'   => lang('Auth.twoFactor.loginTitle'),
            'method'  => $method,
            'email'   => $user instanceof User ? self::maskEmail((string) $user->email) : '',
        ]);
    }

    /**
     * `ana@example.com` → `a**@example.com`.
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1) . str_repeat('*', max(2, mb_strlen($local) - 1)) . ($domain !== '' ? '@' . $domain : '');
    }
}
