<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Modules\Core\Cache\CoreCache;

/**
 * The stored permission catalogue (`permissions` table) and the group grants
 * (`group_permissions`, the source of truth of the group matrix). The catalogue is written only
 * by `modules:sync`; grants by `modules:sync` (default grants) and `GrantManager`.
 */
final class PermissionCatalogue
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly CoreCache $cache = new CoreCache(),
    ) {
    }

    /**
     * Inserts new permissions and updates changed ones. Permissions no longer generated are not
     * deleted: they are returned so that `--prune` can remove them after confirmation.
     *
     * @param array<string, array{module: string, resource: string, operation: string, name: string, description: string}> $catalogue
     *
     * @return array{added: list<string>, updated: list<string>, stale: list<string>}
     */
    public function upsert(array $catalogue): array
    {
        $now    = Time::now()->toDateTimeString();
        $stored = $this->stored();
        $result = ['added' => [], 'updated' => [], 'stale' => []];

        foreach ($catalogue as $name => $entry) {
            if (! isset($stored[$name])) {
                $this->db()->table('permissions')->insert($entry + ['created_at' => $now, 'updated_at' => $now]);
                $result['added'][] = $name;

                continue;
            }

            $row = $stored[$name];

            if ($row['module'] !== $entry['module'] || $row['resource'] !== $entry['resource']
                || $row['operation'] !== $entry['operation'] || $row['description'] !== $entry['description']) {
                $this->db()->table('permissions')->where('name', $name)->update([
                    'module'      => $entry['module'],
                    'resource'    => $entry['resource'],
                    'operation'   => $entry['operation'],
                    'description' => $entry['description'],
                    'updated_at'  => $now,
                ]);
                $result['updated'][] = $name;
            }
        }

        $result['stale'] = array_values(array_diff(array_keys($stored), array_keys($catalogue)));
        sort($result['stale']);

        return $result;
    }

    /**
     * Removes permissions and the grants that name them exactly.
     *
     * @param list<string> $names
     */
    public function prune(array $names): int
    {
        if ($names === []) {
            return 0;
        }

        $this->db()->table('group_permissions')->whereIn('permission', $names)->delete();
        $this->db()->table('permissions')->whereIn('name', $names)->delete();

        return count($names);
    }

    /**
     * Every stored permission by name.
     *
     * @return array<string, array{module: string, resource: string, operation: string, name: string, description: string}>
     */
    public function stored(): array
    {
        $rows = [];

        foreach ($this->db()->table('permissions')->orderBy('name')->get()->getResultArray() as $row) {
            $rows[(string) $row['name']] = [
                'module'      => (string) $row['module'],
                'resource'    => (string) $row['resource'],
                'operation'   => (string) $row['operation'],
                'name'        => (string) $row['name'],
                'description' => (string) $row['description'],
            ];
        }

        return $rows;
    }

    /**
     * Permission names of the enabled modules (disabled modules are hidden, not deleted). Cached.
     *
     * @return list<string>
     */
    public function enabledNames(): array
    {
        return $this->cache->remember('permissions', 'enabled', function (): array {
            $names = $this->db()->table('permissions p')
                ->select('p.name')
                ->join('modules m', 'm.name = p.module')
                ->where('m.enabled', 1)
                ->orderBy('p.name')
                ->get()->getResultArray();

            return array_map(static fn (array $row): string => (string) $row['name'], $names);
        });
    }

    /**
     * Stored grants of a group (atomic names or wildcards).
     *
     * @return list<string>
     */
    public function grants(string $group): array
    {
        $rows = $this->db()->table('group_permissions')->select('permission')
            ->where('group', $group)->orderBy('permission')->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['permission'], $rows);
    }

    /**
     * Every stored grant by group.
     *
     * @return array<string, list<string>>
     */
    public function allGrants(): array
    {
        $grants = [];

        foreach ($this->db()->table('group_permissions')->select('group, permission')->orderBy('group')->orderBy('permission')->get()->getResultArray() as $row) {
            $grants[(string) $row['group']][] = (string) $row['permission'];
        }

        return $grants;
    }

    /**
     * Replaces the grants of a group (callers wrap it in a transaction).
     *
     * @param list<string> $permissions
     */
    public function replaceGrants(string $group, array $permissions): void
    {
        $this->db()->table('group_permissions')->where('group', $group)->delete();
        $this->grant([$group => $permissions]);
    }

    /**
     * Adds grants to groups; existing grants are kept.
     *
     * @param array<string, list<string>> $grants group => permissions
     *
     * @return int Number of grants added
     */
    public function grant(array $grants): int
    {
        $now   = Time::now()->toDateTimeString();
        $added = 0;

        foreach ($grants as $group => $permissions) {
            $existing = array_flip($this->grants($group));

            foreach ($permissions as $permission) {
                if (isset($existing[$permission])) {
                    continue;
                }

                $this->db()->table('group_permissions')->insert([
                    'group'      => $group,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
                $existing[$permission] = true;
                $added++;
            }
        }

        return $added;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
