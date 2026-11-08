<?php

declare(strict_types=1);

namespace Modules\Core\Groups;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Config\Database;
use Modules\Core\Permissions\EscalationGuard;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;
use Throwable;

/**
 * User groups as data (docs/specification.md, "Authentication" › "Groups"; decision D-00-38).
 *
 * The Core `groups` table (name, title, description, reserved, 2FA policy) is the source of
 * truth; Shield reads the groups with `setting('AuthGroups.groups')`, so every change is
 * mirrored there (`mirror()`), with the 2FA policy as each group's `twoFactor` key (read by
 * Auth's `AuthPolicy`).
 *
 * - The four reserved groups (`EscalationGuard::RESERVED_GROUPS`) are created by `modules:sync`
 *   from `Config\AuthGroups::$groups` and can never be renamed or deleted.
 * - Group names never change (only the title, description and 2FA policy do).
 * - A new group receives the grants every reserved group holds (the personal screens: profile,
 *   my sessions, my activity, notifications…).
 * - A group with members cannot be deleted.
 */
final class GroupRegistry
{
    public const TABLE = 'groups';

    public const OPTIONAL = 'optional';

    public const REQUIRED = 'required';

    public const POLICIES = [self::OPTIONAL, self::REQUIRED];

    /** Lower-case letters, digits, `-` and `_`, starting with a letter (Shield group names). */
    public const NAME_PATTERN = '/^[a-z][a-z0-9_-]{1,49}$/';

    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * Creates the reserved groups that are missing (titles and descriptions of
     * `Config\AuthGroups`).
     *
     * @return int Groups created
     */
    public function ensureReserved(): int
    {
        $existing = array_keys($this->all());
        $defaults = config('AuthGroups')->groups;
        $now      = Time::now()->toDateTimeString();
        $created  = 0;

        foreach (EscalationGuard::RESERVED_GROUPS as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->table()->insert([
                'name'        => $name,
                'title'       => (string) ($defaults[$name]['title'] ?? ucfirst($name)),
                'description' => (string) ($defaults[$name]['description'] ?? ''),
                'reserved'    => 1,
                'two_factor'  => self::OPTIONAL,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Every group by name (reserved first, then by creation).
     *
     * @return array<string, array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}>
     */
    public function all(): array
    {
        $groups = [];

        foreach ($this->table()->orderBy('reserved', 'desc')->orderBy('id')->get()->getResultArray() as $row) {
            $groups[(string) $row['name']] = self::normalise($row);
        }

        return $groups;
    }

    /**
     * @return array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}|null
     */
    public function find(string $name): ?array
    {
        $row = $this->table()->where('name', $name)->get()->getRowArray();

        return is_array($row) ? self::normalise($row) : null;
    }

    /**
     * @return array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->table()->where('id', $id)->get()->getRowArray();

        return is_array($row) ? self::normalise($row) : null;
    }

    /**
     * Writes the groups into `setting('AuthGroups.groups')` (what Shield reads).
     */
    public function mirror(): void
    {
        $groups = [];

        foreach ($this->all() as $name => $group) {
            $groups[$name] = [
                'title'       => $group['title'],
                'description' => $group['description'],
                'twoFactor'   => $group['two_factor'],
            ];
        }

        if ($groups !== []) {
            setting()->set('AuthGroups.groups', $groups);
        }
    }

    /**
     * The 2FA policy of every group (from the mirrored setting, so it also works before the
     * table exists): group => `optional` | `required`.
     *
     * @return array<string, string>
     */
    public static function twoFactorPolicy(): array
    {
        try {
            $groups = (array) setting('AuthGroups.groups');
        } catch (Throwable) {
            $groups = config('AuthGroups')->groups;
        }

        $policy = [];

        foreach ($groups as $name => $group) {
            $policy[(string) $name] = is_array($group) && ($group['twoFactor'] ?? null) === self::REQUIRED ? self::REQUIRED : self::OPTIONAL;
        }

        return $policy;
    }

    /**
     * Sets the 2FA policy of a group and mirrors it.
     */
    public function setTwoFactor(string $name, string $policy): void
    {
        if (! in_array($policy, self::POLICIES, true) || $this->find($name) === null) {
            return;
        }

        $this->table()->where('name', $name)->update(['two_factor' => $policy, 'updated_at' => Time::now()->toDateTimeString()]);
        $this->mirror();
    }

    /**
     * Why a name cannot be used for a new group, or null when it can.
     */
    public function nameError(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return lang('Core.groups.invalidName');
        }

        if ((new EscalationGuard($this->catalogue))->isReservedGroup($name) || $this->find($name) !== null) {
            return lang('Core.groups.nameTaken', [$name]);
        }

        return null;
    }

    /**
     * The grants every reserved group holds (the personal screens), given to new groups.
     *
     * @return list<string>
     */
    public function commonGrants(): array
    {
        $common = null;

        foreach (EscalationGuard::RESERVED_GROUPS as $group) {
            $grants = $this->catalogue->grants($group);
            $common = $common === null ? $grants : array_values(array_intersect($common, $grants));
        }

        sort($common);

        return $common;
    }

    /**
     * After a group row was inserted: mirror it and give it the common grants.
     */
    public function created(string $name, ?User $actor = null): void
    {
        $this->mirror();
        (new GrantManager($this->catalogue))->setGroupGrants(null, $name, $this->commonGrants());

        activity_log('Core', 'group_created', [
            'user_id'   => $actor?->id,
            'resource'  => 'groups',
            'record_id' => $name,
            'summary'   => $name,
        ]);
    }

    /**
     * After a group row was updated (title, description, 2FA policy).
     */
    public function changed(): void
    {
        $this->mirror();
    }

    /**
     * Whether a group can be deleted: not reserved and without members.
     */
    public function canDelete(string $name): bool
    {
        return ! (new EscalationGuard($this->catalogue))->isReservedGroup($name) && $this->memberCount($name) === 0;
    }

    /**
     * After a group row was deleted: its grants go and Shield forgets it.
     */
    public function deleted(string $name): void
    {
        Database::connect()->table('group_permissions')->where('group', $name)->delete();

        $groups = (array) setting('AuthGroups.groups');
        unset($groups[$name]);
        setting()->set('AuthGroups.groups', $groups);
        $this->mirror();

        (new GrantManager($this->catalogue))->rebuild();
        Events::trigger('groupDeleted', $name);
    }

    public function memberCount(string $name): int
    {
        return Database::connect()->table(config('Auth')->tables['groups_users'])->where('group', $name)->countAllResults();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}
     */
    private static function normalise(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'title'       => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'reserved'    => (bool) $row['reserved'],
            'two_factor'  => ($row['two_factor'] ?? '') === self::REQUIRED ? self::REQUIRED : self::OPTIONAL,
        ];
    }

    /**
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function table(): BaseBuilder
    {
        return Database::connect()->table(self::TABLE);
    }
}
