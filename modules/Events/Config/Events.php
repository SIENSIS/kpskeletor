<?php

declare(strict_types=1);

/**
 * Events of the Events module (discovered by CodeIgniter, also while the module is disabled):
 *
 * - `backendPathChanged` (`php spark app:backend-path`, D-40-01): source links of calendar
 *   entries written with the old back-office prefix are rewritten (links published through
 *   `backend_link()` use the canonical prefix and need nothing).
 */

use CodeIgniter\Events\Events;
use Modules\Core\Routing\BackendPathChanged;

Events::on('backendPathChanged', static function (BackendPathChanged $event): void {
    $event->rewriteColumn('events', 'source_url');
});
