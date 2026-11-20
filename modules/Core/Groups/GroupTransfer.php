<?php

declare(strict_types=1);

namespace Modules\Core\Groups;

use CodeIgniter\I18n\Time;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Copies the groups of one site to another (`groups:export` / `groups:import`, D-42-04): names,
 * titles, descriptions, the two-factor policy and, optionally, their grants — never their users
 * or any personal data. Used to have a production site's groups on a development copy (e.g. to
 * give a generated module default grants for them) without restoring a full backup.
 *
 * Importing creates the missing groups (with the common grants, like Users › Groups), updates the
 * title, description and 2FA policy of the existing ones, and with grants replaces each group's
 * grants by the exported ones that exist in this site's catalogue (through `GrantManager`:
 * validated, logged as `grants_changed`, `privilegesChanged` for the members). `superadmin`
 * holds every permission and is never changed.
 */
final class GroupTransfer
{
    public const FORMAT = 1;

    public function __construct(
        private readonly GroupRegistry $registry = new GroupRegistry(),
        private readonly PermissionCatalogue $catalogue = new PermissionCatalogue(),
    ) {
    }

    /**
     * @return array{format: int, exported_at: string, groups: list<array<string, mixed>>}
     */
    public function export(bool $withGrants = false): array
    {
        $groups = [];

        foreach ($this->registry->all() as $name => $group) {
            $entry = [
                'name'        => $name,
                'title'       => $group['title'],
                'description' => $group['description'],
                'two_factor'  => $group['two_factor'],
                'reserved'    => $group['reserved'],
            ];

            if ($withGrants && $name !== 'superadmin') {
                $entry['grants'] = $this->catalogue->grants($name);
            }

            $groups[] = $entry;
        }

        return ['format' => self::FORMAT, 'exported_at' => Time::now()->toDateTimeString(), 'groups' => $groups];
    }

    /**
     * @param array<string, mixed> $data an export
     *
     * @return array{created: list<string>, updated: list<string>, grants: list<string>, skippedPermissions: list<string>, errors: list<string>}
     */
    public function import(array $data, bool $withGrants = false): array
    {
        $report = ['created' => [], 'updated' => [], 'grants' => [], 'skippedPermissions' => [], 'errors' => []];

        if (($data['format'] ?? null) !== self::FORMAT || ! is_array($data['groups'] ?? null)) {
            $report['errors'][] = lang('Core.groups.transfer.invalidFile');

            return $report;
        }

        $known = array_keys($this->catalogue->stored());

        foreach ($data['groups'] as $group) {
            $name = is_array($group) && is_string($group['name'] ?? null) ? $group['name'] : '';

            if ($name === '' || preg_match(GroupRegistry::NAME_PATTERN, $name) !== 1) {
                $report['errors'][] = lang('Core.groups.invalidName') . ' (' . $name . ')';

                continue;
            }

            $values = [
                'title'       => mb_substr(trim((string) ($group['title'] ?? $name)), 0, 100),
                'description' => mb_substr(trim((string) ($group['description'] ?? '')), 0, 255),
                'two_factor'  => ($group['two_factor'] ?? '') === GroupRegistry::REQUIRED ? GroupRegistry::REQUIRED : GroupRegistry::OPTIONAL,
            ];

            if ($this->registry->find($name) === null) {
                if (($error = $this->registry->nameError($name)) !== null) {
                    $report['errors'][] = $error;

                    continue;
                }

                $now = Time::now()->toDateTimeString();
                db_connect()->table(GroupRegistry::TABLE)->insert($values + ['name' => $name, 'reserved' => 0, 'created_at' => $now, 'updated_at' => $now]);
                $this->registry->created($name);
                $report['created'][] = $name;
            } else {
                db_connect()->table(GroupRegistry::TABLE)->where('name', $name)->update($values + ['updated_at' => Time::now()->toDateTimeString()]);
                $report['updated'][] = $name;
            }

            if ($withGrants && $name !== 'superadmin' && is_array($group['grants'] ?? null)) {
                $grants  = array_values(array_filter($group['grants'], is_string(...)));
                // Wildcards (`news-api.*`) count when they match a permission of this site.
                $present = array_values(array_filter($grants, static fn (string $grant): bool => str_contains($grant, '*')
                    ? ShieldMatrix::matching($grant, $known) !== []
                    : in_array($grant, $known, true)));

                foreach (array_diff($grants, $present) as $missing) {
                    $report['skippedPermissions'][] = $name . ': ' . $missing;
                }

                (new GrantManager($this->catalogue))->setGroupGrants(null, $name, $present);
                $report['grants'][] = $name;
            }
        }

        $this->registry->changed();

        security_log('Core', 'groups_imported', [
            'resource' => 'groups',
            'summary'  => implode(', ', [...$report['created'], ...$report['updated']]),
            'after'    => ['created' => $report['created'], 'grants' => $report['grants']],
        ]);

        return $report;
    }
}
