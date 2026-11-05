<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Passwords;
use Modules\Auth\Services\LinkTokens;
use Modules\Auth\Services\PasswordChanger;

/**
 * Password reset by email: a single-use link (hashed token) to a form that sets a new password.
 * A reset ends every session of the account and revokes its app tokens.
 */
final class ResetPasswordController extends AuthController
{
    private const VIEW = 'Modules\Auth\Views\reset_request';

    private const FORM = 'Modules\Auth\Views\reset_form';

    public function show(): string
    {
        return $this->render(self::VIEW, ['title' => lang('Auth.ui.resetTitle')]);
    }

    public function send(): ResponseInterface|string
    {
        return $this->sendLink(self::VIEW, lang('Auth.ui.resetTitle'), LinkTokens::RESET_PASSWORD, 'reset-password/new', 'Modules\Auth\Views\emails\reset_password', lang('Auth.mail.resetSubject'));
    }

    /**
     * @return RedirectResponse|string
     */
    public function form()
    {
        $token = $this->request->getGet('token');

        if ((new LinkTokens($this->policy()))->find(LinkTokens::RESET_PASSWORD, $token) === null) {
            return redirect()->to(site_url('reset-password'))->with('error', lang('Auth.msg.linkInvalid'));
        }

        return $this->render(self::FORM, ['title' => lang('Auth.ui.newPasswordTitle'), 'token' => $token]);
    }

    /**
     * @return RedirectResponse|ResponseInterface|string
     */
    public function update()
    {
        $wait = $this->throttle()->loginBlockedFor($this->ip(), '');

        if ($wait > 0) {
            return $this->tooManyAttempts($wait, self::VIEW, ['title' => lang('Auth.ui.resetTitle')]);
        }

        $token    = $this->request->getPost('token');
        $tokens   = new LinkTokens($this->policy());
        $identity = $tokens->find(LinkTokens::RESET_PASSWORD, $token);
        $user     = $identity !== null ? $this->users()->findById($identity->user_id) : null;

        if ($user === null) {
            $this->throttle()->loginFailed($this->ip(), '');

            return redirect()->to(site_url('reset-password'))->with('error', lang('Auth.msg.linkInvalid'));
        }

        $data = [
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
            'email'            => (string) $user->email,
            'username'         => (string) $user->username,
        ];

        if (! $this->validateData($data, self::passwordRules())) {
            return $this->render(self::FORM, [
                'title'  => lang('Auth.ui.newPasswordTitle'),
                'token'  => $token,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $tokens->consume(LinkTokens::RESET_PASSWORD, $token);
        (new PasswordChanger())->change($user, $data['password']);

        return redirect()->to(site_url('login'))->with('message', lang('Auth.msg.passwordReset'));
    }

    /**
     * Rules of a new password: Shield's local `strong_password` validators and the minimum
     * length setting (copied into config('Auth')), plus the confirmation.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    public static function passwordRules(): array
    {
        return [
            'password' => [
                'label' => 'Auth.profile.newPassword',
                'rules' => ['required', Passwords::getMaxLengthRule(), 'strong_password[]'],
            ],
            'password_confirm' => [
                'label' => 'Auth.profile.confirmPassword',
                'rules' => 'required|matches[password]',
            ],
        ];
    }
}
