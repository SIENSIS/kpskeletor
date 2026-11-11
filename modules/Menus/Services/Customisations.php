<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Modules\CacheInvalidator;

/**
 * The stored layer of the back-office menu (tables `backend_menu_customisations` and
 * `backend_menu_links`). Customisations refer to entries by their stable key and a scope (null =
 * everyone, else a group name); custom groups and links are entries `custom.<id>`. Every write
 * invalidates the `menus` cache. Query Builder only.
 */
final class Customisations
{
    public const FIELDS = ['parent', 'zone', 'position', 'labels', 'icon', 'hidden'];
    public const ZONES  = ['sidebar', 'settings', 'user'];
    public const PREFIX = 'custom.';

    /**
     * Every customisation: scope ('' = everyone) => key => fields (null = generated value).
     *
     * @return array<string, array<string, array{parent: ?string, zone: ?string, position: ?int, labels: ?array<string, string>, icon: ?string, hidden: ?bool}>>
     */
    public function all(): array
    {
        $rows   = db_connect()->table('backend_menu_customisations')->orderBy('id')->get()->getResultArray();
        $scopes = [];

        foreach ($rows as $row) {
            $scopes[(string) ($row['group_name'] ?? '')][(string) $row['entry_key']] = self::fields($row);
        }

        return $scopes;
    }

    /**
     * The customisation of one entry in one scope, or null.
     *
     * @return array{parent: ?string, zone: ?string, position: ?int, labels: ?array<string, string>, icon: ?string, hidden: ?bool}|null
     */
    public function get(string $key, ?string $scope): ?array
    {
        return $this->all()[$scope ?? ''][$key] ?? null;
    }

    /**
     * Writes some fields of an entry's customisation (null = back to the generated value); the
     * row is deleted when nothing is left.
     *
     * @param array<string, mixed> $fields subset of FIELDS
     */
    public function put(string $key, ?string $scope, array $fields, ?int $userId = null, bool $invalidate = true): void
    {
        $db      = db_connect();
        $builder = $db->table('backend_menu_customisations')->where('entry_key', $key);
        $scope === null ? $builder->where('group_name', null) : $builder->where('group_name', $scope);
        $row     = $builder->get()->getRowArray();
        $current = is_array($row) ? self::fields($row) : array_fill_keys(self::FIELDS, null);

        foreach ($fields as $field => $value) {
            if (in_array($field, self::FIELDS, true)) {
                $current[$field] = $value;
            }
        }

        $empty = array_filter($current, static fn ($v): bool => $v !== null) === [];

        if ($empty) {
            if (is_array($row)) {
                $db->table('backend_menu_customisations')->where('id', (int) $row['id'])->delete();
            }
        } else {
            $data = [
                'parent'     => $current['parent'],
                'zone'       => $current['zone'],
                'position'   => $current['position'],
                'labels'     => $current['labels'] === null ? null : json_encode($current['labels'], JSON_UNESCAPED_UNICODE),
                'icon'       => $current['icon'],
                'hidden'     => $current['hidden'] === null ? null : (int) $current['hidden'],
                'updated_by' => $userId,
                'updated_at' => Time::now()->toDateTimeString(),
            ];

            if (is_array($row)) {
                $db->table('backend_menu_customisations')->where('id', (int) $row['id'])->update($data);
            } else {
                $db->table('backend_menu_customisations')->insert($data + ['entry_key' => $key, 'group_name' => $scope]);
            }
        }

        if ($invalidate) {
            $this->changed();
        }
    }

    /**
     * Deletes the customisations of an entry (one scope, or every scope when `$everyScope`).
     */
    public function reset(string $key, ?string $scope, bool $everyScope = false): int
    {
        $builder = db_connect()->table('backend_menu_customisations')->where('entry_key', $key);

        if (! $everyScope) {
            $scope === null ? $builder->where('group_name', null) : $builder->where('group_name', $scope);
        }

        $builder->delete();
        $count = db_connect()->affectedRows();
        $this->changed();

        return $count;
    }

    /**
     * Deletes every customisation of a scope (null = everyone), or of every scope.
     */
    public function resetAll(?string $scope, bool $everyScope = false): int
    {
        $builder = db_connect()->table('backend_menu_customisations');

        if ($everyScope) {
            $builder->where('id >', 0);
        } elseif ($scope === null) {
            $builder->where('group_name', null);
        } else {
            $builder->where('group_name', $scope);
        }

        $builder->delete();
        $count = db_connect()->affectedRows();
        $this->changed();

        return $count;
    }

    /**
     * Customisations whose entry does not exist (its module is disabled or removed, or a custom
     * link was deleted): kept but inactive.
     *
     * @param list<string> $known the keys of the generated entries and custom links
     *
     * @return list<array{id: int, key: string, scope: string|null, updated_at: string|null}>
     */
    public function orphans(array $known): array
    {
        $orphans = [];

        foreach (db_connect()->table('backend_menu_customisations')->orderBy('entry_key')->get()->getResultArray() as $row) {
            if (! in_array((string) $row['entry_key'], $known, true)) {
                $orphans[] = ['id' => (int) $row['id'], 'key' => (string) $row['entry_key'], 'scope' => $row['group_name'] === null ? null : (string) $row['group_name'], 'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at']];
            }
        }

        return $orphans;
    }

    /**
     * @param list<int> $ids
     */
    public function deleteRows(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        db_connect()->table('backend_menu_customisations')->whereIn('id', $ids)->delete();
        $count = db_connect()->affectedRows();
        $this->changed();

        return $count;
    }

    /**
     * Custom groups and links: id => row (labels and groups decoded).
     *
     * @return array<int, array{id: int, kind: string, type: string, value: string, labels: array<string, string>, icon: string, target: string, permission: string, groups: list<string>, parent: ?string, zone: string, position: int}>
     */
    public function links(): array
    {
        $links = [];

        foreach (db_connect()->table('backend_menu_links')->orderBy('id')->get()->getResultArray() as $row) {
            $links[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'kind'       => (string) $row['kind'] === 'group' ? 'group' : 'link',
                'type'       => (string) $row['type'],
                'value'      => (string) $row['value'],
                'labels'     => self::decodeLabels($row['labels'] ?? null) ?? [],
                'icon'       => (string) $row['icon'],
                'target'     => (string) $row['target'] === '_blank' ? '_blank' : '_self',
                'permission' => (string) $row['permission'],
                'groups'     => array_values(array_map('strval', (array) (json_decode((string) ($row['groups'] ?? '[]'), true) ?: []))),
                'parent'     => $row['parent'] === null || $row['parent'] === '' ? null : (string) $row['parent'],
                'zone'       => in_array((string) $row['zone'], self::ZONES, true) ? (string) $row['zone'] : 'sidebar',
                'position'   => (int) $row['position'],
            ];
        }

        return $links;
    }

    /**
     * Inserts or updates a custom group or link (values already validated).
     *
     * @param array{kind: string, type: string, value: string, labels: array<string, string>, icon: string, target: string, permission: string, groups: list<string>, parent: ?string, zone: string, position: int} $link
     */
    public function saveLink(?int $id, array $link, ?int $userId): int
    {
        $db   = db_connect();
        $now  = Time::now()->toDateTimeString();
        $data = [
            'kind'       => $link['kind'],
            'type'       => $link['type'],
            'value'      => $link['value'],
            'labels'     => json_encode($link['labels'], JSON_UNESCAPED_UNICODE),
            'icon'       => $link['icon'],
            'target'     => $link['target'],
            'permission' => $link['permission'],
            'groups'     => json_encode($link['groups']),
            'parent'     => $link['parent'],
            'zone'       => $link['zone'],
            'position'   => $link['position'],
            'updated_at' => $now,
        ];

        if ($id === null) {
            $db->table('backend_menu_links')->insert($data + ['created_by' => $userId, 'created_at' => $now]);
            $id = (int) $db->insertID();
        } else {
            $db->table('backend_menu_links')->where('id', $id)->update($data);
        }

        $this->changed();

        return $id;
    }

    /** Deletes a custom group or link and its customisations; its children go back to their own place. */
    public function deleteLink(int $id): void
    {
        $db  = db_connect();
        $key = self::PREFIX . $id;
        $db->table('backend_menu_links')->where('id', $id)->delete();
        $db->table('backend_menu_customisations')->where('entry_key', $key)->delete();
        $db->table('backend_menu_customisations')->where('parent', $key)->update(['parent' => null]);
        $db->table('backend_menu_links')->where('parent', $key)->update(['parent' => null]);
        $this->changed();
    }

    public function changed(): void
    {
        (new CacheInvalidator())->invalidate(['menus']);
    }

    /**
     * @return array<string, string>|null
     */
    public static function decodeLabels(mixed $json): ?array
    {
        if (! is_string($json) || $json === '') {
            return null;
        }

        $labels = json_decode($json, true);

        if (! is_array($labels)) {
            return null;
        }

        $labels = array_filter(array_map(static fn ($l): string => is_scalar($l) ? (string) $l : '', $labels), static fn (string $l): bool => $l !== '');

        return $labels === [] ? null : $labels;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{parent: ?string, zone: ?string, position: ?int, labels: ?array<string, string>, icon: ?string, hidden: ?bool}
     */
    private static function fields(array $row): array
    {
        return [
            'parent'   => $row['parent'] === null ? null : (string) $row['parent'],
            'zone'     => $row['zone'] === null ? null : (string) $row['zone'],
            'position' => $row['position'] === null ? null : (int) $row['position'],
            'labels'   => self::decodeLabels($row['labels'] ?? null),
            'icon'     => $row['icon'] === null ? null : (string) $row['icon'],
            'hidden'   => $row['hidden'] === null ? null : (bool) $row['hidden'],
        ];
    }
}
