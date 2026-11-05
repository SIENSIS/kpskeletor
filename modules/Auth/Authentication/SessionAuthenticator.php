<?php

declare(strict_types=1);

namespace Modules\Auth\Authentication;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;

/**
 * Shield's session authenticator with one addition: issuing the remember-me token after the 2FA
 * step. Shield issues it inside `attempt()`, before a login action has completed, so a cookie
 * obtained with the password alone could log in once the pending 2FA identity is gone (for
 * example after an admin reset). The login form therefore asks Shield not to remember a user
 * with 2FA and `UserTwoFactorAction` calls `rememberVerifiedUser()` once the code is right.
 */
class SessionAuthenticator extends Session
{
    /**
     * Session key: the user ticked "remember me" and their login is waiting for the 2FA code.
     */
    public const REMEMBER_AFTER_2FA = 'kp_auth_remember_after_2fa';

    public function rememberVerifiedUser(User $user): void
    {
        if ((bool) setting('Auth.sessionConfig')['allowRemembering']) {
            $this->rememberUser($user);
        }
    }
}
