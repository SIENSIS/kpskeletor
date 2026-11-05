<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\EmailCodes;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\RecoveryCodes;
use Modules\Auth\Services\SessionTracker;
use Modules\Auth\Services\TwoFactor;

/**
 * Re-authentication screen (`auth/reauth`, auth layout): the password and, when the user has
 * 2FA, a code of their method (or a recovery code). Wrong answers count as failed logins; past
 * the limit the session ends. Success opens the window and returns to the remembered page.
 */
final class ReauthController extends AuthController
{
    private const VIEW = 'Modules\Auth\Views\reauth';

    /**
     * @return RedirectResponse|string
     */
    public function show()
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        return $this->render(self::VIEW, $this->data($user));
    }

    /**
     * @return RedirectResponse|ResponseInterface|string
     */
    public function confirm()
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        $email = (string) $user->email;

        if ($this->throttle()->loginBlockedFor($this->ip(), $email) > 0) {
            return $this->endSession(lang('Auth.reauth.tooMany'));
        }

        $twoFactor = new TwoFactor();
        $method    = $twoFactor->method((int) $user->id);
        $password  = $this->request->getPost('password');
        $code      = $this->postedString('code', 20);
        $recovery  = $this->postedString('recovery_code', 40);
        $valid     = is_string($password) && $password !== '' && mb_strlen($password) <= 1024
            && service('passwords')->verify($password, (string) $user->password_hash);

        if ($valid && $method !== null) {
            if ($recovery !== '') {
                $valid = (new RecoveryCodes())->consume((int) $user->id, $recovery);
            } elseif ($method === TwoFactor::TOTP) {
                $valid = $twoFactor->verifyTotp((int) $user->id, $code);
            } else {
                $valid = (new EmailCodes())->consumeForReauth($user, $code);
            }
        }

        if (! $valid) {
            $this->throttle()->loginFailed($this->ip(), $email);
            log_message('notice', 'Failed re-authentication of user {id}.', ['id' => $user->id]);

            if ($this->throttle()->loginBlockedFor($this->ip(), $email) > 0) {
                return $this->endSession(lang('Auth.reauth.tooMany'));
            }

            return $this->render(self::VIEW, $this->data($user) + ['error' => lang('Auth.reauth.wrong')]);
        }

        $this->throttle()->loginSucceeded($email);

        $reauth = new Reauth($this->policy());
        $reauth->confirm($user);
        log_message('info', 'User {id} re-authenticated.', ['id' => $user->id]);

        $return = $reauth->takeReturn();

        return redirect()->to($return !== null ? site_url(ltrim($return, '/')) : config('Auth')->loginRedirect())
            ->with('success', lang('Auth.reauth.confirmed', [(int) ($this->policy()->reauthWindow() / MINUTE)]));
    }

    /**
     * Sends a code by email (users whose method is `email`), within the email limits.
     */
    public function sendCode(): RedirectResponse
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        if ((new TwoFactor())->method((int) $user->id) !== TwoFactor::EMAIL) {
            return redirect()->to(site_url('auth/reauth'));
        }

        if ($this->throttle()->linkRequestFromIp($this->ip()) > 0 || ! $this->throttle()->linkRequestForAccount((string) $user->email)) {
            return redirect()->to(site_url('auth/reauth'))->with('error', lang('Auth.twoFactor.tooManyCodes'));
        }

        (new EmailCodes())->issueForReauth($user);

        return redirect()->to(site_url('auth/reauth'))->with('message', lang('Auth.twoFactor.codeSent'));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(User $user): array
    {
        return [
            'title'   => lang('Auth.reauth.title'),
            'user'    => $user,
            'method'  => (new TwoFactor())->method((int) $user->id),
        ];
    }

    private function postedString(string $field, int $max): string
    {
        $value = $this->request->getPost($field);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    private function endSession(string $message): RedirectResponse
    {
        (new SessionTracker())->endCurrent(SessionTracker::END_LOGOUT);
        auth('session')->logout();

        return redirect()->to(site_url('login'))->withCookies()->with('error', $message);
    }
}
