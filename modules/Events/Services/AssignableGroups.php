<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\EscalationGuard;
use Modules\Core\Permissions\PermissionCatalogue;

/**
 * The groups a user may target with a `restricted` event (D-24-02): an editor cannot create an
 * event only for superadmins.
 *
 * - superadmins: every group;
 * - anybody else: never `superadmin`; a group they belong to, or a group whose every permission
 *   they hold themselves (the rule of the Core's `EscalationGuard` for adding someone to a group).
 *
 * Used for the options of the groups field (kpcrudy only saves offered options) and checked again
 * on save, so a forged id is refused with an error instead of being dropped silently.
 */
final class AssignableGroups
{
    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * Ids of the groups the user may target.
     *
     * @return list<int>
     */
    public function ids(User $user): array
    {
        $ids = [];

        foreach ((new GroupRegistry($this->catalogue))->all() as $name => $group) {
            if ($this->allows($user, $name)) {
                $ids[] = $group['id'];
            }
        }

        return $ids;
    }

    public function allows(User $user, string $group): bool
    {
        if (Authorization::isSuperadmin($user)) {
            return true;
        }

        if ($group === Authorization::SUPERADMIN) {
            return false;
        }

        if (in_array($group, $user->getGroups() ?? [], true)) {
            return true;
        }

        $guard         = new EscalationGuard($this->catalogue);
        $authorization = Authorization::for($user, $this->catalogue);

        foreach ($guard->expand($this->catalogue->grants($group), true) as $permission) {
            if (! $authorization->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
