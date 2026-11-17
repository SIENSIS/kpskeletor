<?php

declare(strict_types=1);

/**
 * Events of the Tasks module (discovered by CodeIgniter, also while the module is disabled, so
 * the listeners check it):
 *
 * - `filesAccessPolicies` (the Files module, tutorial 10): registers `AttachmentPolicy` for the
 *   folder `modules/tasks` (the Files module itself skips policies of disabled modules);
 * - `privilegesChanged` (the Core's `GrantManager`, tutorial 6, "listening to an event of another
 *   module"): a user who can no longer use the board is removed from the open to-do tasks.
 */

use CodeIgniter\Events\Events;
use Modules\Core\Registry\ModuleState;
use Modules\Files\Policies\PolicyRegistry;
use Modules\Tasks\Services\Assignees;
use Modules\Tasks\Storage\AttachmentPolicy;

Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
    $registry->register(new AttachmentPolicy());
});

Events::on('privilegesChanged', static function (mixed $userId): void {
    if (is_numeric($userId) && (new ModuleState())->isEnabled('Tasks')) {
        Assignees::dropIfNoAccess((int) $userId);
    }
});
