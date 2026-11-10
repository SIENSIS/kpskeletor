<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use DateTimeZone;
use Modules\Auth\Models\UserProfileModel;
use Modules\Auth\Services\Impersonation;
use Modules\Auth\Services\LoginListener;
use Modules\Auth\Services\LoginThrottle;
use Modules\Auth\Services\PasswordChanger;
use Modules\Auth\Services\SessionTracker;
use Modules\Core\Activity\ActivityLogger;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Frontend\ThemeMode;

/**
 * The user's own profile (resource `auth-profile`, granted to every group): name, email,
 * language, time zone, colour mode, birth date and the password (the email and the password
 * cannot be changed while impersonating). Changing the email asks for
 * the current password; changing the password ends the other sessions.
 */
final class ProfileController extends BackendController
{
    public function index(): string
    {
        $user = $this->user();

        $this->breadcrumb(lang('Auth.profile.title'));

        return $this->render('Modules\Auth\Views\profile', [
            'title'     => lang('Auth.profile.title'),
            'icon'      => 'bi bi-person-circle',
            'user'      => $user,
            'profile'   => model(UserProfileModel::class)->forUser((int) $user->id),
            'locales'   => config('App')->supportedLocales,
            'timezones' => DateTimeZone::listIdentifiers(),
            'modes'     => ThemeMode::MODES,
            'canEdit'   => $this->can('auth-profile.edit'),
            'errors'    => session()->getFlashdata('errors') ?? [],
        ]);
    }

    public function update(): RedirectResponse
    {
        $user  = $this->user();
        $data  = [
            'name'       => trim((string) $this->request->getPost('name')),
            'email'      => trim((string) $this->request->getPost('email')),
            'locale'     => (string) $this->request->getPost('locale'),
            'timezone'   => (string) $this->request->getPost('timezone'),
            'theme_mode' => (string) $this->request->getPost('theme_mode'),
            'birth_date' => trim((string) $this->request->getPost('birth_date')),
        ];
        $rules = [
            'name'       => ['label' => 'Auth.profile.name', 'rules' => 'permit_empty|max_length[150]'],
            'email'      => ['label' => 'Auth.profile.email', 'rules' => 'required|max_length[254]|valid_email'],
            'locale'     => ['label' => 'Auth.profile.locale', 'rules' => 'required|in_list[' . implode(',', config('App')->supportedLocales) . ']'],
            'timezone'   => ['label' => 'Auth.profile.timezone', 'rules' => 'required|timezone'],
            'theme_mode' => ['label' => 'Auth.profile.themeMode', 'rules' => 'required|in_list[' . implode(',', ThemeMode::MODES) . ']'],
            'birth_date' => ['label' => 'Auth.profile.birthDate', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $emailChanged = mb_strtolower($data['email']) !== mb_strtolower((string) $user->email);

        if ($emailChanged) {
            $error = Impersonation::active() ? lang('Auth.impersonation.refused') : $this->checkEmailChange($user, $data['email']);

            if ($error !== null) {
                return redirect()->back()->withInput()->with('errors', ['email' => $error]);
            }
        }

        $profile = [
            'name'       => $data['name'] === '' ? null : $data['name'],
            'locale'     => $data['locale'],
            'timezone'   => $data['timezone'],
            'theme_mode' => $data['theme_mode'],
            'birth_date' => $data['birth_date'] === '' ? null : $data['birth_date'],
        ];
        $old = model(UserProfileModel::class)->forUser((int) $user->id);
        model(UserProfileModel::class)->saveFor((int) $user->id, $profile);

        [$before, $after] = ActivityLogger::diff($old, $profile);

        if ($after !== []) {
            activity_log('Auth', 'profile_updated', ['resource' => 'auth-profile', 'record_id' => $user->id, 'before' => $before, 'after' => $after]);
        }

        if ($emailChanged) {
            $previous    = (string) $user->email;
            $user->email = $data['email'];
            model(UserModel::class)->save($user);
            security_log('Auth', 'email_changed', ['resource' => 'auth-profile', 'record_id' => $user->id, 'before' => ['email' => $previous], 'after' => ['email' => $data['email']]]);
        }

        LoginListener::rememberThemeMode($data['theme_mode']);

        return redirect()->to(site_url('backend/profile'))->withCookies()->with('success', lang('Auth.profile.saved'));
    }

    public function password(): RedirectResponse|string
    {
        $user = $this->user();

        if (Impersonation::active()) {
            return redirect()->to(site_url('backend/profile'))->with('error', lang('Auth.impersonation.refused'));
        }

        $this->breadcrumb(lang('Auth.profile.title'), site_url('backend/profile'))->breadcrumb(lang('Auth.profile.passwordTitle'));

        return $this->render('Modules\Auth\Views\password', [
            'title'     => lang('Auth.profile.passwordTitle'),
            'icon'      => 'bi bi-key',
            'forced'    => $user->requiresPasswordReset(),
            'minLength' => (int) config('Auth')->minimumPasswordLength,
            'errors'    => session()->getFlashdata('errors') ?? [],
        ]);
    }

    /**
     * @return RedirectResponse|ResponseInterface
     */
    public function changePassword()
    {
        if (Impersonation::active()) {
            return redirect()->to(site_url('backend/profile'))->with('error', lang('Auth.impersonation.refused'));
        }

        $user     = $this->user();
        $throttle = new LoginThrottle();
        $ip       = $this->request->getIPAddress();
        $wait     = $throttle->loginBlockedFor($ip, (string) $user->email);

        if ($wait > 0) {
            return redirect()->to(site_url('backend/profile/password'))
                ->with('error', lang('Auth.msg.throttled', [max(1, (int) ceil($wait / 60))]));
        }

        $current = $this->request->getPost('current_password');

        if (! is_string($current) || ! service('passwords')->verify($current, (string) $user->password_hash)) {
            $throttle->loginFailed($ip, (string) $user->email);

            return redirect()->to(site_url('backend/profile/password'))->with('errors', ['current_password' => lang('Auth.profile.currentPasswordWrong')]);
        }

        $data = [
            'password'         => (string) $this->request->getPost('password'),
            'password_confirm' => (string) $this->request->getPost('password_confirm'),
            'email'            => (string) $user->email,
            'username'         => (string) $user->username,
        ];

        if (! $this->validateData($data, ResetPasswordController::passwordRules())) {
            return redirect()->to(site_url('backend/profile/password'))->with('errors', $this->validator->getErrors());
        }

        $result  = (new PasswordChanger())->change($user, $data['password']);
        $tracker = new SessionTracker();
        $row     = $tracker->current();

        if ($row !== null) {
            $tracker->regenerate($row);
        }

        return redirect()->to(site_url('backend/profile'))->with('success', lang('Auth.profile.passwordChanged', [$result['sessions']]));
    }

    /**
     * Changing the email needs the current password and an address no other account uses.
     */
    private function checkEmailChange(User $user, string $email): ?string
    {
        // Same attempt limit as the login and the password change: the session holder must not be
        // able to guess the account password through this form.
        $throttle = new LoginThrottle();
        $ip       = $this->request->getIPAddress();
        $wait     = $throttle->loginBlockedFor($ip, (string) $user->email);

        if ($wait > 0) {
            return lang('Auth.msg.throttled', [max(1, (int) ceil($wait / 60))]);
        }

        $password = $this->request->getPost('current_password');

        if (! is_string($password) || ! service('passwords')->verify($password, (string) $user->password_hash)) {
            $throttle->loginFailed($ip, (string) $user->email);

            return lang('Auth.profile.emailNeedsPassword');
        }

        $other = model(UserModel::class)->findByCredentials(['email' => $email]);

        if ($other !== null && (int) $other->id !== (int) $user->id) {
            return lang('Auth.profile.emailTaken');
        }

        return null;
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
