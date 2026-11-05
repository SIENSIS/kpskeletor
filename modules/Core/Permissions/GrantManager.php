<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Config\Database;
use Modules\Core\Modules\CacheInvalidator;
use Throwable;

/**
 * Changes group grants and individual user permissions (the permission matrix screen of
 * session 15 and the console use it):
 *
 * - only atomic names of the catalogue and Shield wildcards that match some of them are stored;
 * - grants are completed with their implied permissions (`ImpliedPermissions`);
 * - an actor (null = the console or the installer) passes the escalation rules (`EscalationGuard`);
 * - saving a group is atomic (one transaction), then Shield's matrix is written again
 *   (`ShieldMatrix`) and every affected user gets the `privilegesChanged` event (Auth regenerates
 *   their sessions).
 */
final class GrantManager
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue = new PermissionCatalogue(),
        private readonly ?EscalationGuard $guard = null,
        private readonly ?ShieldMatrix $matrix = null,
    ) {
    }

    /**
     * Replaces the grants of a group.
     *
     * @param list<string> $grants
     *
     * @return list<string> The grants stored (validated, implied permissions added)
     *
     * @throws EscalationException
     */
    public function setGroupGrants(?User $actor, string $group, array $grants): array
    {
        $guard = $this->guard();

        if (! $guard->groupExists($group)) {
            throw new EscalationException(lang('Core.rbac.unknownGroup', [$group]));
        }

        $grants = $this->complete($this->validated($grants));
        $added  = array_values(array_diff($grants, $this->catalogue->grants($group)));

        if ($actor !== null && (! $guard->canChangeGroupGrants($actor, $group) || ! $guard->canGrant($actor, $added))) {
            throw new EscalationException(lang('Core.rbac.cannotGrant', [$group]));
        }

        $db = Database::connect();
        $db->transException(true)->transStart();

        try {
            $this->catalogue->replaceGrants($group, $grants);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        $this->matrix()->write();
        (new CacheInvalidator())->invalidate(['menus']); // menus are cached per group set

        foreach ($this->members($group) as $userId) {
            Events::trigger('privilegesChanged', $userId);
        }

        log_message('notice', 'Grants of group {group} set to [{grants}] by {actor}.', [
            'group'  => $group,
            'grants' => implode(', ', $grants),
            'actor'  => $actor === null ? 'the console' : 'user ' . $actor->id,
        ]);

        return $grants;
    }

    /**
     * Gives a user individual permissions (Shield's `addPermission()`; atomic names only).
     *
     * @param list<string> $permissions
     *
     * @return list<string> The permissions added (with their implied permissions)
     *
     * @throws EscalationException
     */
    public function grantToUser(?User $actor, User $target, array $permissions): array
    {
        $permissions = $this->complete($this->validated($permissions, false));
        $this->assertCanChangeUser($actor, $target, $permissions);

        $enabled = $this->catalogue->enabledNames();

        foreach ($permissions as $permission) {
            if (! in_array($permission, $enabled, true)) {
                throw new EscalationException(lang('Core.rbac.unknownPermission', [$permission]));
            }
        }

        $target->addPermission(...$permissions);
        $this->userChanged($actor, $target, 'granted', $permissions);

        return $permissions;
    }

    /**
     * Removes individual permissions of a user.
     *
     * @param list<string> $permissions
     *
     * @throws EscalationException
     */
    public function revokeFromUser(?User $actor, User $target, array $permissions): void
    {
        $permissions = array_values(array_unique(array_map(static fn (string $p): string => strtolower(trim($p)), $permissions)));
        $this->assertCanChangeUser($actor, $target, []);

        $target->removePermission(...$permissions);
        $this->userChanged($actor, $target, 'revoked', $permissions);
    }

    /**
     * Writes Shield's matrix again (after a sync or a module enabled/disabled).
     */
    public function rebuild(): void
    {
        $this->matrix()->write();
        (new CacheInvalidator())->invalidate(['menus']);
    }

    /**
     * Keeps valid names: atomic catalogue names and (when allowed) wildcards matching some.
     *
     * @param list<string> $grants
     *
     * @return list<string>
     *
     * @throws EscalationException naming the first invalid grant
     */
    private function validated(array $grants, bool $wildcards = true): array
    {
        $names = array_keys($this->catalogue->stored());
        $valid = [];

        foreach ($grants as $grant) {
            $grant = strtolower(trim($grant));

            if ($grant === '') {
                continue;
            }

            $ok = str_contains($grant, '*')
                ? $wildcards && ShieldMatrix::matching($grant, $names) !== []
                : in_array($grant, $names, true);

            if (! $ok) {
                throw new EscalationException(lang('Core.rbac.unknownPermission', [$grant]));
            }

            $valid[] = $grant;
        }

        return $valid;
    }

    /**
     * @param list<string> $grants
     *
     * @return list<string>
     */
    private function complete(array $grants): array
    {
        return ImpliedPermissions::complete($grants, ImpliedPermissions::operationsOf($this->catalogue->stored()));
    }

    /**
     * @param list<string> $adding
     *
     * @throws EscalationException
     */
    private function assertCanChangeUser(?User $actor, User $target, array $adding): void
    {
        if ($actor === null) {
            return;
        }

        $guard = $this->guard();

        if (! $guard->canChangeUserPermissions($actor, $target) || ! $guard->canGrant($actor, $adding)) {
            throw new EscalationException(lang('Core.rbac.cannotChangeUser'));
        }
    }

    /**
     * @param list<string> $permissions
     */
    private function userChanged(?User $actor, User $target, string $change, array $permissions): void
    {
        Events::trigger('privilegesChanged', (int) $target->id);

        log_message('notice', 'Permissions [{permissions}] {change} to user {target} by {actor}.', [
            'permissions' => implode(', ', $permissions),
            'change'      => $change,
            'target'      => $target->id,
            'actor'       => $actor === null ? 'the console' : 'user ' . $actor->id,
        ]);
    }

    /**
     * @return list<int>
     */
    private function members(string $group): array
    {
        $rows = db_connect()->table(config('Auth')->tables['groups_users'])->select('user_id')
            ->where('group', $group)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    private function guard(): EscalationGuard
    {
        return $this->guard ?? new EscalationGuard($this->catalogue);
    }

    private function matrix(): ShieldMatrix
    {
        return $this->matrix ?? new ShieldMatrix($this->catalogue);
    }
}
