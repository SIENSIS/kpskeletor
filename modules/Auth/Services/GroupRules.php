<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Exceptions\GroupRuleException;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\EscalationGuard;

/**
 * Changes a user's groups under the escalation rules of the Core (`EscalationGuard`,
 * docs/specification.md, "RBAC" and "Groups"):
 *
 * - `superadmin` bypasses RBAC (`Authorization`);
 * - the four groups `superadmin`, `admin`, `editor` and `user` cannot be renamed or deleted;
 * - only a superadmin can assign or remove `superadmin` or change a superadmin's groups, and
 *   the last superadmin cannot lose it;
 * - a user can only add someone to a group whose permissions they hold;
 * - nobody changes their own groups.
 *
 * Every change regenerates the session ids of the affected user (SessionTracker).
 */
final class GroupRules
{
    public const SUPERADMIN = 'superadmin';

    public const RESERVED = EscalationGuard::RESERVED_GROUPS;

    public function __construct(private readonly EscalationGuard $guard = new EscalationGuard())
    {
    }

    public static function isSuperadmin(?User $user): bool
    {
        return Authorization::isSuperadmin($user);
    }

    public function isReserved(string $group): bool
    {
        return $this->guard->isReservedGroup($group);
    }

    public function canRenameOrDelete(string $group): bool
    {
        return $this->guard->canRenameOrDelete($group);
    }

    public function canAssign(User $actor, User $target, string $group): bool
    {
        return $this->guard->canAddToGroup($actor, $target, $group);
    }

    public function canRemove(User $actor, User $target, string $group): bool
    {
        return $this->guard->canRemoveFromGroup($actor, $target, $group);
    }

    /**
     * @throws GroupRuleException
     */
    public function assign(User $actor, User $target, string $group): void
    {
        if (! $this->canAssign($actor, $target, $group)) {
            throw new GroupRuleException(lang('Auth.groups.cannotAssign', [$group]));
        }

        $target->addGroup($group);
        $this->changed($target, 'added to', $group, $actor);
    }

    /**
     * @throws GroupRuleException
     */
    public function remove(User $actor, User $target, string $group): void
    {
        if (! $this->canRemove($actor, $target, $group)) {
            throw new GroupRuleException(lang('Auth.groups.cannotRemove', [$group]));
        }

        $target->removeGroup($group);
        $this->changed($target, 'removed from', $group, $actor);
    }

    public function superadminCount(): int
    {
        return $this->guard->superadminCount();
    }

    private function changed(User $target, string $change, string $group, User $actor): void
    {
        (new SessionTracker())->privilegesChanged((int) $target->id);

        activity_log('Auth', $change === 'added to' ? 'group_added' : 'group_removed', [
            'user_id'   => $actor->id,
            'resource'  => 'users',
            'record_id' => $target->id,
            'summary'   => $group,
            'changes'   => ['user' => (string) $target->username, 'group' => $group],
        ]);
    }
}
