<?php

declare(strict_types=1);

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use Modules\Auth\Models\UserProfileModel;
use Modules\Auth\Services\AuthPolicy;
use Modules\Auth\Services\Impersonation;
use Modules\Auth\Services\SessionTracker;
use Throwable;

/**
 * Global session hardening (`authsession`), before every request:
 *
 * 1. copies the stored Auth settings into Shield's `config('Auth')` (AuthPolicy);
 * 2. caps remember-me: a token older than `Auth.rememberLifetime` is deleted, so it can never
 *    re-create a session after its own lifetime (Shield extends it on every use);
 * 3. for a logged-in session: ends it when it was logged out remotely or passed the absolute
 *    lifetime (`Auth.absoluteLifetime`; remember-me may then re-create it), regenerates the
 *    session id after a privilege change, locks it after `Auth.idleTimeout` and records activity;
 * 4. applies the user's language;
 * 5. while impersonating: ends an expired impersonation (the request is not run; back to the
 *    impersonator's back office) and shows the impersonation banner in the top bar.
 *
 * Sessions re-created by remember-me are tracked as "remembered".
 */
final class AuthSession implements FilterInterface
{
    public const LOCK_KEY = 'kp_auth_locked';

    public const ACTIVITY_KEY = 'kp_auth_activity';

    /** The page to return to after unlocking (a path of this site). */
    public const RETURN_KEY = 'kp_auth_lock_return';

    /** Paths reachable while the session is locked. */
    private const UNLOCKED_PATHS = ['auth/lock', 'logout', 'auth/captcha'];

    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || is_cli() && ENVIRONMENT !== 'testing') {
            return null;
        }

        try {
            AuthPolicy::apply();

            return $this->check($request);
        } catch (Throwable $e) {
            if (str_contains($e::class, 'Database')) {
                // Before installation (no tables): nothing to track.
                return null;
            }

            throw $e;
        }
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * @return RedirectResponse|ResponseInterface|null
     */
    private function check(IncomingRequest $request)
    {
        $policy  = new AuthPolicy();
        $tracker = new SessionTracker();

        $this->capRememberMe($request, $policy);

        $info   = session((string) config('Auth')->sessionConfig['field']);
        $userId = is_array($info) ? ($info['id'] ?? null) : null;

        if ($userId === null) {
            $this->loginByRememberMe($tracker);

            return null;
        }

        if (! empty($info['auth_action'])) {
            // Pending login (2FA): tracked once it completes.
            return null;
        }

        if (Impersonation::expired()) {
            // Never run the pending request as the impersonator: back to their profile.
            (new Impersonation())->stop(Impersonation::END_EXPIRED);

            return $this->toBackend($request, lang('Auth.impersonation.expired'));
        }

        $row = $tracker->current();

        if ($row === null) {
            $user = model(UserModel::class)->findById($userId);

            if ($user === null) {
                return null;
            }

            $row = $tracker->start($user, false);
        }

        if ($row['ended_at'] !== null) {
            $this->endPhpSession();
            $tracker->forgetSelector($row['remember_selector'] ?? null);

            return $this->toLogin($request, lang('Auth.msg.sessionRevoked'));
        }

        if (strtotime((string) $row['created_at']) + $policy->absoluteLifetime() <= time()) {
            $tracker->end((int) $row['id'], SessionTracker::END_EXPIRED);
            $this->endPhpSession();

            if ($this->loginByRememberMe($tracker)) {
                return null;
            }

            return $this->toLogin($request, lang('Auth.msg.sessionExpired'));
        }

        if ((int) $row['regenerate'] === 1) {
            $tracker->regenerate($row);
        }

        $locked = $this->idleLock($request, $policy);

        if ($locked !== null) {
            return $locked;
        }

        $tracker->touch($row);
        $this->applyLocale($request, (int) $userId);

        if (Impersonation::active()) {
            // After the locale, so the banner speaks the page's language.
            $this->impersonationBanner((int) $userId);
        }

        return null;
    }

    /**
     * Deletes a remember-me token older than its own lifetime before Shield can use it.
     */
    private function capRememberMe(IncomingRequest $request, AuthPolicy $policy): void
    {
        $config = config('Auth');
        $cookie = $request->getCookie((string) config('Cookie')->prefix . $config->sessionConfig['rememberCookieName']);

        if (! is_string($cookie) || ! str_contains($cookie, ':')) {
            return;
        }

        $selector = explode(':', $cookie, 2)[0];
        $table    = db_connect()->table($config->tables['remember_tokens']);
        $token    = $table->where('selector', $selector)->get()->getRowArray();

        if ($token === null) {
            return;
        }

        $issued = strtotime((string) ($token['created_at'] ?? ''));

        if ($policy->rememberLifetime() === 0 || $issued === false || $issued + $policy->rememberLifetime() <= time()) {
            db_connect()->table($config->tables['remember_tokens'])->where('selector', $selector)->delete();
        }
    }

    /**
     * Lets Shield log the user in with a valid remember-me cookie and tracks the new session.
     */
    private function loginByRememberMe(SessionTracker $tracker): bool
    {
        if (! (bool) config('Auth')->sessionConfig['allowRemembering'] || $tracker->rememberSelector() === null) {
            return false;
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if (! $authenticator->loggedIn()) {
            return false;
        }

        $user = $authenticator->getUser();

        if (! $user instanceof User) {
            return false;
        }

        $tracker->start($user, true);
        activity_log('Auth', 'login_remembered', ['user_id' => $user->id, 'resource' => 'auth-profile']);

        return true;
    }

    /**
     * Ends the PHP session without touching remember-me tokens (unlike Shield's logout).
     */
    private function endPhpSession(): void
    {
        $session = session();

        foreach (array_keys((array) $session->get()) as $key) {
            $session->remove((string) $key);
        }

        $session->regenerate(true);
        Services::resetSingle('auth');
    }

    private function idleLock(IncomingRequest $request, AuthPolicy $policy): ?ResponseInterface
    {
        $session  = session();
        $timeout  = $policy->idleTimeout();
        $path     = trim($request->getPath(), '/');
        $exempt   = in_array($path, self::UNLOCKED_PATHS, true);
        $activity = $session->get(self::ACTIVITY_KEY);

        if ($timeout > 0 && is_int($activity) && $activity + $timeout <= time()) {
            $session->set(self::LOCK_KEY, true);
        }

        if ($session->get(self::LOCK_KEY) === true) {
            if ($exempt) {
                return null;
            }

            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(401)->setJSON(['error' => lang('Auth.lock.locked')]);
            }

            if ($request->getMethod() === 'GET') {
                $query = $request->getUri()->getQuery();
                $session->set(self::RETURN_KEY, '/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : ''));
            }

            return redirect()->to(site_url('auth/lock'));
        }

        $session->set(self::ACTIVITY_KEY, time());

        return null;
    }

    private function toBackend(IncomingRequest $request, string $message): ResponseInterface
    {
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setJSON(['error' => $message]);
        }

        return redirect()->to(site_url('backend'))->with('warning', $message);
    }

    /**
     * The banner of an impersonation (top bar of the back-office layout): who is being
     * impersonated, until when, and the button back to the impersonator's account.
     */
    private function impersonationBanner(int $userId): void
    {
        $user  = model(UserModel::class)->findById($userId);
        $state = Impersonation::state();

        if (! $user instanceof User || $state === null) {
            return;
        }

        service('theme')->regions()->add('topbar', view('Modules\Auth\Views\impersonation_banner', [
            'username' => (string) $user->username,
            'expires'  => $state['expires'],
        ], ['saveData' => false]), 0);
    }

    private function toLogin(IncomingRequest $request, string $message): ResponseInterface
    {
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setJSON(['error' => $message]);
        }

        return redirect()->to(site_url('login'))->withCookies()->with('warning', $message);
    }

    private function applyLocale(IncomingRequest $request, int $userId): void
    {
        $locale = model(UserProfileModel::class)->forUser($userId)['locale'] ?? null;

        if (is_string($locale) && in_array($locale, config('App')->supportedLocales, true)) {
            $request->setLocale($locale);
            service('language')->setLocale($locale);
        }
    }
}
