<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Services\AuthPolicy;
use Modules\Auth\Services\Captcha;
use Modules\Auth\Services\LinkTokens;
use Modules\Auth\Services\LoginThrottle;
use Modules\Auth\Services\SecurityMailer;
use Modules\Core\Controllers\FrontendController;

/**
 * Base of the public authentication screens: the theme's `auth` layout, the captcha, the rate
 * limits and the generic answers that never reveal whether an account exists.
 */
abstract class AuthController extends FrontendController
{
    protected AuthPolicy $policy;

    protected LoginThrottle $throttle;

    protected Captcha $captcha;

    protected function layout(): string
    {
        return theme_layout('auth');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        $this->policy ??= new AuthPolicy();

        return parent::render($view, $data + [
            'captcha'  => $this->policy->captcha(),
            'remember' => (bool) config('Auth')->sessionConfig['allowRemembering'],
        ]);
    }

    protected function policy(): AuthPolicy
    {
        return $this->policy ??= new AuthPolicy();
    }

    protected function throttle(): LoginThrottle
    {
        return $this->throttle ??= new LoginThrottle(null, $this->policy());
    }

    protected function captchaService(): Captcha
    {
        return $this->captcha ??= new Captcha($this->policy());
    }

    /**
     * True when the captcha is off or the posted answer is right (single use).
     */
    protected function captchaPassed(): bool
    {
        return ! $this->captchaService()->enabled() || $this->captchaService()->verify($this->request->getPost('captcha'));
    }

    protected function postedEmail(): string
    {
        $email = $this->request->getPost('email');

        return is_string($email) ? mb_substr(trim($email), 0, 254) : '';
    }

    protected function ip(): string
    {
        return $this->request->getIPAddress();
    }

    /**
     * A 429 answer with Retry-After, rendering the form with the throttling message.
     *
     * @param array<string, mixed> $data
     */
    protected function tooManyAttempts(int $seconds, string $view, array $data = []): ResponseInterface
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        security_log('Auth', 'throttled', ['summary' => '/' . ltrim($this->request->getPath(), '/')]);

        return $this->response
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $seconds)
            ->setBody($this->render($view, $data + ['error' => lang('Auth.msg.throttled', [$minutes])]));
    }

    /**
     * Spends the time of a password hash for an unknown account, so the answer takes as long as
     * for a real one (no enumeration by timing).
     */
    protected function dummyPasswordCheck(string $password): void
    {
        static $hash = null;
        $hash ??= service('passwords')->hash('kp-dummy-password-' . bin2hex(random_bytes(4)));

        service('passwords')->verify($password, (string) $hash);
    }

    /**
     * Handles a magic-link or reset request: the same generic page whether the account exists
     * or not; the email is sent only to an existing, active account within its hourly limit.
     *
     * @param string $view     Form view (shown again on errors)
     * @param string $type     LinkTokens::MAGIC_LINK or LinkTokens::RESET_PASSWORD
     * @param string $route    Path of the link, the token is added as `?token=`
     * @param string $mailView Email view
     */
    protected function sendLink(string $view, string $title, string $type, string $route, string $mailView, string $subject): ResponseInterface|string
    {
        $data = ['title' => $title, 'email' => $this->postedEmail()];
        $wait = $this->throttle()->linkRequestFromIp($this->ip());

        if ($wait > 0) {
            return $this->tooManyAttempts($wait, $view, $data);
        }

        if (! $this->captchaPassed()) {
            return $this->render($view, $data + ['error' => lang('Auth.msg.captchaWrong')]);
        }

        $email = $data['email'];

        if (! $this->validateData(['email' => $email], ['email' => 'required|max_length[254]|valid_email'])) {
            return $this->render($view, $data + ['error' => lang('Auth.msg.invalidEmail')]);
        }

        $user = $this->users()->findByCredentials(['email' => $email]);

        if ($user !== null && ! $user->isBanned() && $user->active && $this->throttle()->linkRequestForAccount($email)) {
            $token = (new LinkTokens($this->policy()))->issue($user, $type);

            (new SecurityMailer())->send($user, $type, $subject, $mailView, [
                'url'       => site_url($route) . '?token=' . $token,
                'ipAddress' => $this->ip(),
                'device'    => (string) $this->request->getUserAgent(),
                'date'      => date('Y-m-d H:i:s'),
                'minutes'   => (int) ceil($this->policy()->linkLifetime() / 60),
            ]);
        }

        return $this->render('Modules\Auth\Views\message', [
            'title'   => lang('Auth.ui.checkEmailTitle'),
            'message' => lang('Auth.msg.linkSent'),
        ]);
    }

    protected function users(): UserModel
    {
        return model(UserModel::class);
    }
}
