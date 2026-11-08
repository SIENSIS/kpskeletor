<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Shield\Entities\User;

/**
 * Escalation rules of the RBAC (docs/specification.md, "RBAC" › "Escalation rules"), shared by
 * the group rules of Auth, `GrantManager` and the Users screens (session 15):
 *
 * - nobody changes their own groups or individual permissions;
 * - a user can only grant permissions they hold (wildcards: every permission they match), and
 *   can only add someone to a group whose permissions they hold;
 * - only a superadmin touches the `superadmin` group, a superadmin's groups or permissions, and
 *   the grants of a group they belong to;
 * - the reserved groups cannot be renamed, deleted or created again;
 * - the last superadmin cannot lose the group;
 * - only a superadmin manages a superadmin's account, and a user can only manage accounts that
 *   hold nothing they do not hold (`canManageUser()`).
 *
 * Superadmins pass every rule but the first and the last.
 */
final class EscalationGuard
{
    public const RESERVED_GROUPS = ['superadmin', 'admin', 'editor', 'user'];

    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    public function isReservedGroup(string $group): bool
    {
        return in_array(strtolower(trim($group)), self::RESERVED_GROUPS, true);
    }

    public function groupExists(string $group): bool
    {
        return array_key_exists($group, (array) setting('AuthGroups.groups'));
    }

    /**
     * Whether `$actor` may add `$target` to `$group`.
     */
    public function canAddToGroup(User $actor, User $target, string $group): bool
    {
        if (! $this->groupExists($group) || $this->isSelf($actor, $target)) {
            return false;
        }

        if (Authorization::isSuperadmin($actor)) {
            return true;
        }

        if ($group === Authorization::SUPERADMIN || Authorization::isSuperadmin($target)) {
            return false;
        }

        return $this->holdsAll($actor, $this->groupPermissions($group));
    }

    /**
     * Whether `$actor` may remove `$target` from `$group`.
     */
    public function canRemoveFromGroup(User $actor, User $target, string $group): bool
    {
        if ($this->isSelf($actor, $target)) {
            return false;
        }

        if ($group === Authorization::SUPERADMIN) {
            return Authorization::isSuperadmin($actor) && $this->superadminCount() > 1;
        }

        return Authorization::isSuperadmin($actor) || ! Authorization::isSuperadmin($target);
    }

    /**
     * Whether `$actor` may change the individual permissions of `$target`.
     */
    public function canChangeUserPermissions(User $actor, User $target): bool
    {
        if ($this->isSelf($actor, $target)) {
            return false;
        }

        return Authorization::isSuperadmin($actor) || ! Authorization::isSuperadmin($target);
    }

    /**
     * Whether `$actor` may manage `$target`'s account (edit it, ban, force a password reset,
     * reset 2FA, log out everywhere): superadmins manage everybody; anybody else only users who
     * are not superadmins and hold nothing the actor does not hold (their groups' grants and
     * individual permissions), so nobody can take over a stronger account. Actions on oneself
     * are decided by the caller.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if (Authorization::isSuperadmin($actor)) {
            return true;
        }

        if (Authorization::isSuperadmin($target)) {
            return false;
        }

        if ($this->isSelf($actor, $target)) {
            return true;
        }

        $permissions = $target->getPermissions() ?? [];

        foreach ($target->getGroups() ?? [] as $group) {
            $permissions = [...$permissions, ...$this->groupPermissions((string) $group)];
        }

        return $this->holdsAll($actor, $this->expand(array_values(array_unique($permissions)), true));
    }

    /**
     * Whether `$actor` may change the grants of `$group` at all (what they add is checked with
     * `canGrant()`).
     */
    public function canChangeGroupGrants(User $actor, string $group): bool
    {
        if (! $this->groupExists($group)) {
            return false;
        }

        if (Authorization::isSuperadmin($actor)) {
            return true;
        }

        return $group !== Authorization::SUPERADMIN && ! $actor->inGroup($group);
    }

    /**
     * Whether `$actor` holds every permission the grants give (atomic names or wildcards).
     *
     * @param list<string> $grants
     */
    public function canGrant(User $actor, array $grants): bool
    {
        return $this->holdsAll($actor, $this->expand($grants));
    }

    /**
     * Whether `$actor` may rename or delete `$group`, or create a group with this name.
     */
    public function canRenameOrDelete(string $group): bool
    {
        return ! $this->isReservedGroup($group);
    }

    public function superadminCount(): int
    {
        return db_connect()->table(config('Auth')->tables['groups_users'])
            ->where('group', Authorization::SUPERADMIN)->countAllResults();
    }

    /**
     * The catalogue permissions the grants give (wildcards expanded; names outside the
     * catalogue are kept, so that nobody but a superadmin can grant them).
     *
     * @param list<string> $grants
     * @param bool         $enabledOnly Only permissions of enabled modules (what the grants give now)
     *
     * @return list<string>
     */
    public function expand(array $grants, bool $enabledOnly = false): array
    {
        $names    = $enabledOnly ? $this->catalogue->enabledNames() : array_keys($this->catalogue->stored());
        $expanded = [];

        foreach ($grants as $grant) {
            $grant = strtolower($grant);

            if ($enabledOnly && ! ShieldMatrix::effective($grant, $names)) {
                continue;
            }

            foreach (str_contains($grant, '*') ? ShieldMatrix::matching($grant, $names) : [$grant] as $name) {
                $expanded[$name] = true;
            }
        }

        return array_keys($expanded);
    }

    /**
     * @return list<string>
     */
    private function groupPermissions(string $group): array
    {
        return $this->expand($this->catalogue->grants($group), true);
    }

    /**
     * @param list<string> $permissions
     */
    private function holdsAll(User $actor, array $permissions): bool
    {
        if (Authorization::isSuperadmin($actor)) {
            return true;
        }

        $authorization = Authorization::for($actor, $this->catalogue);

        foreach ($permissions as $permission) {
            if (! $authorization->can($permission)) {
                return false;
            }
        }

        return true;
    }

    private function isSelf(User $actor, User $target): bool
    {
        return (int) $actor->id === (int) $target->id;
    }
}
