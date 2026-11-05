<?php

declare(strict_types=1);

/**
 * Authentication events of the Auth module (discovered by CodeIgniter).
 *
 * - `login` (Shield, after a password or magic-link login): start tracking the session,
 *   recognise the device (new-device notice) and restore the user's colour mode;
 * - `logout` (Shield): end the tracked session;
 * - `privilegesChanged` (Core's GrantManager: a user's permissions or groups' grants changed):
 *   regenerate the user's session ids.
 */

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\LoginListener;
use Modules\Auth\Services\SessionTracker;

Events::on('login', static function (User $user): void {
    (new LoginListener())->login($user);
});

Events::on('logout', static function (?User $user = null): void {
    (new LoginListener())->logout();
});

Events::on('privilegesChanged', static function (int $userId): void {
    (new SessionTracker())->privilegesChanged($userId);
});
