<?php

declare(strict_types=1);

/**
 * Events of the Core (discovered by CodeIgniter).
 *
 * - `pre_system`: refuse to boot in production without a usable `app.maskKey` (the shared key of
 *   kpcrudy and kpapy, `Modules\Core\Security\MaskKey`). Web requests only: `spark` keeps working
 *   so the key can be generated (installer, session 18) and checked (`security:check`).
 */

use CodeIgniter\Events\Events;
use Modules\Core\Security\MaskKey;

Events::on('pre_system', static function (): void {
    if (! is_cli()) {
        MaskKey::assertBootable();
    }
});
