<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Exceptions\GroupRuleException;

/**
 * The rules of the reserved groups (docs/specification.md, "Groups"):
 *
 * - `superadmin` bypasses RBAC (`isSuperadmin()`, used by the `can` filter);
 * - the four groups `superadmin`, `admin`, `editor` and `user` cannot be renamed or deleted;
 * - only a superadmin can assign or remove `superadmin`, and the last superadmin cannot lose it;
 * - nobody changes their own groups.
 *
 * Every change regenerates the session ids of the affected user (SessionTracker).
 */
final class GroupRules
{
    public const SUPERADMIN = 'superadmin';

    public const RESERVED = ['superadmin', 'admin', 'editor', 'user'];

    public static function isSuperadmin(?User $user): bool
    {
        return $user !== null && $user->inGroup(self::SUPERADMIN);
    }

    public function isReserved(string $group): bool
    {
        return in_array($group, self::RESERVED, true);
    }

    public function canRenameOrDelete(string $group): bool
    {
        return ! $this->isReserved($group);
    }

    public function canAssign(User $actor, User $target, string $group): bool
    {
        if (! array_key_exists($group, (array) setting('AuthGroups.groups')) || (int) $actor->id === (int) $target->id) {
            return false;
        }

        return $group !== self::SUPERADMIN || self::isSuperadmin($actor);
    }

    public function canRemove(User $actor, User $target, string $group): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        if ($group !== self::SUPERADMIN) {
            return true;
        }

        return self::isSuperadmin($actor) && $this->superadminCount() > 1;
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
        return db_connect()->table(config('Auth')->tables['groups_users'])->where('group', self::SUPERADMIN)->countAllResults();
    }

    private function changed(User $target, string $change, string $group, User $actor): void
    {
        (new SessionTracker())->privilegesChanged((int) $target->id);

        log_message('notice', 'User {target} {change} group {group} by user {actor}.', [
            'target' => $target->id,
            'change' => $change,
            'group'  => $group,
            'actor'  => $actor->id,
        ]);
    }
}
