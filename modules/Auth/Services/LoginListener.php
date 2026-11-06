<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Controllers\TwoFactorSettingsController;
use Modules\Auth\Filters\AuthSession;
use Modules\Auth\Models\UserProfileModel;
use Modules\Core\Frontend\ThemeMode;

/**
 * What happens after Shield's `login` and `logout` events.
 */
final class LoginListener
{
    public function login(User $user): void
    {
        (new SessionTracker())->start($user, false);

        session()->set(AuthSession::ACTIVITY_KEY, time());
        session()->remove(AuthSession::LOCK_KEY);

        (new DeviceRecognizer())->loggedIn($user);

        $reauth = new Reauth();
        $reauth->forget();

        if (session(Reauth::PASSWORD_LOGIN_KEY) === true) {
            // Password (and 2FA code) typed just now: sensitive actions need no second prompt.
            $reauth->confirm($user);
        }

        session()->remove(Reauth::PASSWORD_LOGIN_KEY);
        session()->remove(TwoFactorSettingsController::PENDING_SECRET);

        $mode = model(UserProfileModel::class)->forUser((int) $user->id)['theme_mode'] ?? null;

        if (is_string($mode) && in_array($mode, ThemeMode::MODES, true)) {
            self::rememberThemeMode($mode);
        }

        activity_log('Auth', 'login', ['user_id' => $user->id, 'resource' => 'auth-profile', 'summary' => (string) $user->email]);
    }

    public function logout(?User $user = null): void
    {
        if ($user !== null) {
            activity_log('Auth', 'logout', ['user_id' => $user->id, 'resource' => 'auth-profile']);
        }

        (new Reauth())->forget();
        session()->remove([TwoFactorSettingsController::PENDING_SECRET, TwoFactorSettingsController::NEW_CODES]);

        (new SessionTracker())->endCurrent(SessionTracker::END_LOGOUT);
    }

    /**
     * The colour mode is read from the `kp_theme` cookie (decision D-04-06): the profile's
     * choice is copied there on login and when the profile is saved.
     */
    public static function rememberThemeMode(string $mode): void
    {
        service('response')->setCookie(ThemeMode::COOKIE, $mode, YEAR, '', '/', (string) config('Cookie')->prefix, (bool) config('Cookie')->secure, false, 'Lax');
    }
}
