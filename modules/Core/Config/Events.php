<?php

declare(strict_types=1);

/**
 * Events of the Core (discovered by CodeIgniter).
 *
 * - `pre_system`: refuse to boot in production without a usable `app.maskKey` (the shared key of
 *   kpcrudy and kpapy, `Modules\Core\Security\MaskKey`). Web requests only: `spark` keeps working
 *   so the key can be generated (`app:install`) and checked (`security:check`). Before the
 *   application is configured (no `.env`, no lock file) the only page is the web installer,
 *   which generates the key, so the check waits until then; meanwhile the debug toolbar
 *   (development only) drops Shield's collector, which reads the database.
 */

use CodeIgniter\Events\Events;
use Config\Toolbar;
use Modules\Core\Security\MaskKey;

Events::on('pre_system', static function (): void {
    if (is_cli()) {
        return;
    }

    if (service('installstate')->isConfigured()) {
        MaskKey::assertBootable();

        return;
    }

    $toolbar             = config(Toolbar::class);
    $toolbar->collectors = array_values(array_filter($toolbar->collectors, static fn (string $collector): bool => ! str_starts_with($collector, 'CodeIgniter\\Shield\\')));
});
