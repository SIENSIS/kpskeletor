<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use Modules\Auth\Services\LinkTokens;

/**
 * Magic-link login: a single-use link (hashed token, `Auth.magicLinkLifetime`) sent by email.
 */
final class MagicLinkController extends AuthController
{
    private const VIEW = 'Modules\Auth\Views\magic_link';

    /**
     * @return RedirectResponse|string
     */
    public function show()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->to(site_url('login'))->with('error', lang('Auth.magicLinkDisabled'));
        }

        return $this->render(self::VIEW, ['title' => lang('Auth.ui.magicLinkTitle')]);
    }

    /**
     * @return RedirectResponse|ResponseInterface|string
     */
    public function send()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->to(site_url('login'))->with('error', lang('Auth.magicLinkDisabled'));
        }

        return $this->sendLink(self::VIEW, lang('Auth.ui.magicLinkTitle'), LinkTokens::MAGIC_LINK, 'login/verify-magic-link', 'Modules\Auth\Views\emails\magic_link', lang('Auth.mail.magicLinkSubject'));
    }

    /**
     * @return RedirectResponse|ResponseInterface
     */
    public function verify()
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->to(site_url('login'))->with('error', lang('Auth.magicLinkDisabled'));
        }

        if ($this->request->getUserAgent()->isRobot()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $wait = $this->throttle()->loginBlockedFor($this->ip(), '');

        if ($wait > 0) {
            return $this->tooManyAttempts($wait, self::VIEW, ['title' => lang('Auth.ui.magicLinkTitle')]);
        }

        $userId = (new LinkTokens($this->policy()))->consume(LinkTokens::MAGIC_LINK, $this->request->getGet('token'));
        $user   = $userId !== null ? $this->users()->findById($userId) : null;

        if ($user === null || $user->isBanned()) {
            $this->throttle()->loginFailed($this->ip(), '');

            return redirect()->to(site_url('login/magic-link'))->with('error', lang('Auth.msg.linkInvalid'));
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->loggedIn()) {
            $authenticator->logout();
        }

        if ($authenticator->startUpAction('login', $user) && $authenticator->hasAction($user->id)) {
            $authenticator->setPendingLoginMethod(Session::ID_TYPE_MAGIC_LINK);

            return redirect()->route('auth-action-show')->withCookies();
        }

        $authenticator->setPendingLoginMethod(Session::ID_TYPE_MAGIC_LINK);
        $authenticator->loginById($user->id);
        $this->throttle()->loginSucceeded((string) $user->email);

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }
}
