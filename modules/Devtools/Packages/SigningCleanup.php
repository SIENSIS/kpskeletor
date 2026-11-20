<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

use CodeIgniter\Autoloader\FileLocatorCached;
use Modules\Auth\Services\SessionTracker;
use Modules\Core\Cache\CacheClearer;

/**
 * The cleanup after a signature made from the local form (D-43-01, owner's addition): run by the
 * request that follows the signing POST (the debug toolbar writes the POST's own file in an
 * *after* filter, so only a later request can remove it). The services behind
 * `php spark debugbar:clear`, `logs:clear`, `cache:clear` and `app:cache-clear` are called (no
 * process is spawned); then the session's flash and old-input data are dropped and the session
 * is ended (the user signs in again). Prevention (nothing collected or stored in the first place)
 * stays mandatory: this is the second line.
 */
final class SigningCleanup
{
    /**
     * @return list<string> what was done (for the activity log)
     */
    public function run(): array
    {
        helper('filesystem');
        $done = [];

        foreach (['debugbar' => 'debugbar:clear', 'logs' => 'logs:clear'] as $folder => $command) {
            if (is_dir(WRITEPATH . $folder)) {
                delete_files(WRITEPATH . $folder, false, true);
            }

            $done[] = $command;
        }

        cache()->clean();
        $locator = service('locator');

        if ($locator instanceof FileLocatorCached) {
            $locator->deleteCache();
        }

        $done[] = 'cache:clear';

        (new CacheClearer())->clear();
        $done[] = 'app:cache-clear';

        $this->endSession();
        $done[] = 'session';

        return $done;
    }

    /**
     * Drops the flash and old-input data, ends the tracked session, logs out and destroys the
     * PHP session.
     */
    public function endSession(): void
    {
        $session = session();

        foreach ($session->getFlashKeys() as $key) {
            $session->remove($key);
        }

        $session->remove('_ci_old_input');

        if (auth('session')->loggedIn()) {
            (new SessionTracker())->endCurrent(SessionTracker::END_LOGOUT);
            auth('session')->logout();
        }

        $session->destroy();
    }
}
