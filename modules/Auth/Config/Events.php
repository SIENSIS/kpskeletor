<?php

declare(strict_types=1);

/**
 * Authentication events of the Auth module (discovered by CodeIgniter).
 *
 * - `login` (Shield, after a password or magic-link login): start tracking the session,
 *   recognise the device (new-device notice) and restore the user's colour mode;
 * - `logout` (Shield): end the tracked session;
 * - `privilegesChanged` (Core's GrantManager: a user's permissions or groups' grants changed):
 *   regenerate the user's session ids;
 * - `twoFactorReset` (TwoFactor::reset(), an administrator's reset): notify the user (mandatory
 *   notification TwoFactorReset);
 * - `install.superadmin` (Core's Installer, `php spark app:install`): offer 2FA to the first
 *   superadmin.
 *
 * It also tells the Core's notifications the language each user reads them in (the profile's
 * locale), so queued emails are rendered in it.
 */

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Models\UserProfileModel;
use Modules\Auth\Notifications\TwoFactorReset;
use Modules\Auth\Services\InstallTwoFactor;
use Modules\Auth\Services\LoginListener;
use Modules\Auth\Services\SessionTracker;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallPrompt;
use Modules\Core\Notifications\RecipientLocale;

Events::on('login', static function (User $user): void {
    (new LoginListener())->login($user);
});

Events::on('logout', static function (?User $user = null): void {
    (new LoginListener())->logout($user);
});

Events::on('privilegesChanged', static function (int $userId): void {
    (new SessionTracker())->privilegesChanged($userId);
});

Events::on('twoFactorReset', static function (User $user, ?int $byUserId = null): void {
    notify($user, new TwoFactorReset(['by' => $byUserId]));
});

Events::on(Installer::EVENT_SUPERADMIN, static function (User $user, ?InstallPrompt $prompt = null): void {
    (new InstallTwoFactor())->offer($user, $prompt);
});

RecipientLocale::resolveWith(static function (User $user): ?string {
    $locale = model(UserProfileModel::class)->forUser((int) $user->id)['locale'] ?? null;

    return is_string($locale) ? $locale : null;
});
