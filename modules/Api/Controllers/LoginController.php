<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpapy\Auth\Context;
use Kpapy\Auth\LoginController as KpapyLoginController;
use Kpapy\Http\ApiException;
use Modules\Auth\Services\LoginThrottle;
use Modules\Auth\Services\PasswordChanger;

/**
 * The app login (`POST api/v1/auth/token` → `POST api/v1/auth/token/verify`), refresh and logout:
 * kpapy's controller with the Auth module's rules.
 *
 * - Wrong passwords count in Auth's `LoginThrottle` (per IP and per account, shared with the web
 *   login form); while it blocks, the answer is `429` with `Retry-After`, before the password
 *   is checked.
 * - A user who must change their password first gets `403 password-reset-required`.
 * - Only tokens from app logins can be refreshed; personal tokens keep their own expiry (`403`).
 */
final class LoginController extends KpapyLoginController
{
    public function refresh(): ResponseInterface
    {
        if ((Context::token()->secret2 ?? null) === PasswordChanger::PERSONAL_TOKEN) {
            $this->setup();

            return $this->responder->problem(new ApiException(403, 'personal-token', lang('Api.login.personalRefresh')));
        }

        return parent::refresh();
    }

    protected function checkCredentials(string $email, string $password): object
    {
        $throttle = new LoginThrottle();
        $ip       = $this->request->getIPAddress();
        $wait     = $throttle->loginBlockedFor($ip, $email);

        if ($wait > 0) {
            throw ApiException::tooManyRequests($wait);
        }

        try {
            $user = parent::checkCredentials($email, $password);
        } catch (ApiException $e) {
            $throttle->loginFailed($ip, $email);

            throw $e;
        }

        $throttle->loginSucceeded($email);

        if (method_exists($user, 'requiresPasswordReset') && $user->requiresPasswordReset()) {
            throw new ApiException(403, 'password-reset-required', lang('Api.login.passwordReset'));
        }

        return $user;
    }
}
