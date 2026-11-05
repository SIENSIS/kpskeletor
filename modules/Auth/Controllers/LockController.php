<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Filters\AuthSession;
use Modules\Auth\Services\SessionTracker;

/**
 * Idle lock screen (`Auth.idleTimeout`): the session stays open but asks for the password again.
 * Wrong passwords count against the account's login limit; past it the session ends.
 */
final class LockController extends AuthController
{
    private const VIEW = 'Modules\Auth\Views\lock';

    /**
     * @return RedirectResponse|string
     */
    public function show()
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        if (session(AuthSession::LOCK_KEY) !== true) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return $this->render(self::VIEW, ['title' => lang('Auth.lock.title'), 'user' => $user]);
    }

    /**
     * @return RedirectResponse|ResponseInterface|string
     */
    public function unlock()
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        if (session(AuthSession::LOCK_KEY) !== true) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        $email = (string) $user->email;
        $data  = ['title' => lang('Auth.lock.title'), 'user' => $user];

        if ($this->throttle()->loginBlockedFor($this->ip(), $email) > 0) {
            auth('session')->logout();

            return redirect()->to(site_url('login'))->with('error', lang('Auth.lock.tooMany'));
        }

        $password = $this->request->getPost('password');

        if (! is_string($password) || ! service('passwords')->verify($password, (string) $user->password_hash)) {
            $this->throttle()->loginFailed($this->ip(), $email);

            return $this->render(self::VIEW, $data + ['error' => lang('Auth.lock.wrongPassword')]);
        }

        $this->throttle()->loginSucceeded($email);

        $session = session();
        $session->remove(AuthSession::LOCK_KEY);
        $session->set(AuthSession::ACTIVITY_KEY, time());

        $row = (new SessionTracker())->current();

        if ($row !== null) {
            (new SessionTracker())->regenerate($row);
        }

        $return = $session->get(AuthSession::RETURN_KEY);
        $session->remove(AuthSession::RETURN_KEY);

        $target = is_string($return) && str_starts_with($return, '/') && ! str_starts_with($return, '//') && ! str_contains($return, '\\')
            ? site_url(ltrim($return, '/'))
            : config('Auth')->loginRedirect();

        return redirect()->to($target);
    }
}
