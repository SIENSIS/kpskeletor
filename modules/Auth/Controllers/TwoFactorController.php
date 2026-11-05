<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use Modules\Auth\Authentication\UserTwoFactorAction;

/**
 * The 2FA step of a login (Shield's action routes `auth-action-*`), rendered in the theme's
 * auth layout. Only reachable while a login is pending with `UserTwoFactorAction`.
 */
final class TwoFactorController extends AuthController
{
    public function show(): string
    {
        return $this->action()->show();
    }

    public function handle(): RedirectResponse
    {
        return $this->action()->handle($this->request);
    }

    /**
     * @return RedirectResponse|ResponseInterface|string
     */
    public function verify()
    {
        return $this->action()->verify($this->request);
    }

    public function cancel(): RedirectResponse
    {
        return $this->action()->abandon();
    }

    private function action(): UserTwoFactorAction
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        $action        = $authenticator->isPending() ? $authenticator->getAction() : null;

        if (! $action instanceof UserTwoFactorAction) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $action;
    }
}
