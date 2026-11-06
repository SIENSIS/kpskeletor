<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use Modules\Auth\Authentication\SessionAuthenticator;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Activity\ActivityLogger;

/**
 * Password login and logout.
 *
 * Every failure (unknown email, wrong password, wrong captcha) gets the same generic message;
 * failures count per IP and per account and answer 429 past the limits.
 */
final class LoginController extends AuthController
{
    private const VIEW = 'Modules\Auth\Views\login';

    /**
     * @return RedirectResponse|string
     */
    public function show()
    {
        if (auth('session')->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return $this->render(self::VIEW, ['title' => lang('Auth.ui.loginTitle')]);
    }

    /**
     * @return RedirectResponse|ResponseInterface
     */
    public function attempt()
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        $email    = $this->postedEmail();
        $password = $this->request->getPost('password');
        $password = is_string($password) ? $password : '';
        $data     = ['title' => lang('Auth.ui.loginTitle'), 'email' => $email];
        $wait     = $this->throttle()->loginBlockedFor($this->ip(), $email);

        if ($wait > 0) {
            return $this->tooManyAttempts($wait, self::VIEW, $data);
        }

        if (! $this->captchaPassed()) {
            $this->throttle()->loginFailed($this->ip(), $email);

            return $this->failed($data, lang('Auth.msg.captchaWrong'));
        }

        $user = $email !== '' ? $this->users()->findByCredentials(['email' => $email]) : null;

        if ($user === null) {
            $this->dummyPasswordCheck($password);
        }

        if ($email === '' || $password === '' || mb_strlen($password) > 1024) {
            $this->throttle()->loginFailed($this->ip(), $email);

            return $this->failed($data, lang('Auth.msg.badLogin'));
        }

        $remember = (bool) $this->request->getPost('remember') && (bool) config('Auth')->sessionConfig['allowRemembering'];

        // With 2FA, remember-me is issued only after the code (SessionAuthenticator).
        $twoFactor = $remember && $user !== null && (new TwoFactor())->enabled((int) $user->id);
        session()->remove(SessionAuthenticator::REMEMBER_AFTER_2FA);

        if ($twoFactor) {
            $remember = false;
            session()->set(SessionAuthenticator::REMEMBER_AFTER_2FA, true);
        }

        // A password login (with its 2FA step) counts as a fresh re-authentication (LoginListener).
        session()->set(Reauth::PASSWORD_LOGIN_KEY, true);
        $result = $authenticator->remember($remember)->attempt(['email' => $email, 'password' => $password]);

        if (! $result->isOK()) {
            session()->remove([Reauth::PASSWORD_LOGIN_KEY, SessionAuthenticator::REMEMBER_AFTER_2FA]);
            $this->throttle()->loginFailed($this->ip(), $email);

            // Only someone who knows the password learns that the account is banned.
            $message = $user !== null && $user->isBanned() && service('passwords')->verify($password, (string) $user->password_hash)
                ? ($user->getBanMessage() ?? lang('Auth.bannedUser'))
                : lang('Auth.msg.badLogin');

            return $this->failed($data, $message);
        }

        $this->throttle()->loginSucceeded($email);

        // The remember-me and device cookies were set on the shared response.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show')->withCookies();
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }

    /**
     * GET logout: a confirmation form (logging out changes state, so it needs a POST with CSRF).
     *
     * @return RedirectResponse|string
     */
    public function confirmLogout()
    {
        if (! auth('session')->loggedIn()) {
            return redirect()->to(site_url('login'));
        }

        return $this->render('Modules\Auth\Views\logout', ['title' => lang('Auth.ui.logoutTitle')]);
    }

    public function logout(): RedirectResponse
    {
        if (auth('session')->loggedIn()) {
            auth('session')->logout();
        }

        return redirect()->to(config('Auth')->logoutRedirect())->withCookies()->with('message', lang('Auth.msg.loggedOut'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function failed(array $data, string $message): ResponseInterface
    {
        // The typed email is stored as data (never interpolated), and only if it is an email.
        security_log('Auth', 'login_failed', ['user_id' => null, 'resource' => 'auth-profile', 'summary' => ActivityLogger::typedEmail($data['email'] ?? null)]);

        return $this->response->setStatusCode(200)->setBody($this->render(self::VIEW, $data + ['error' => $message]));
    }
}
