<?php

declare(strict_types=1);

namespace Modules\Dashboard\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Widgets\WidgetInterface;
use Modules\Core\Widgets\WidgetOptions;
use Modules\Core\Widgets\WidgetRegistry;
use Modules\Core\Widgets\WidgetSizes;

/**
 * The dashboard layouts (docs/widgets.md):
 *
 * - **group layout** (`dashboard_group_widgets`): per group and widget, whether it is enabled,
 *   its default position and size and its options. A widget without a row is enabled with its
 *   defaults (manifest order, first size, default options), so a new module's widgets appear
 *   for the groups that hold their permission;
 * - **available widgets** of a user: the widgets they hold the permission of **and** that are
 *   enabled for one of their groups; position, size and options come from the first of their
 *   groups (in `GroupRegistry` order: reserved first) that enables the widget;
 * - **user layout** (`dashboard_user_layouts`): the user's order, sizes (within the widget's
 *   sizes) and hidden widgets, applied over the available widgets; widgets they gained later are
 *   added at the end. "Restore the group layout" deletes it.
 */
final class DashboardLayout
{
    public const GROUPS = 'dashboard_group_widgets';
    public const USERS  = 'dashboard_user_layouts';

    /** @var array<string, array<string, array{enabled: bool, position: int, size: string, options: array<string, mixed>}>> */
    private array $groupCache = [];

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly WidgetRegistry $widgets = new WidgetRegistry(),
        private readonly WidgetOptions $options = new WidgetOptions(),
        private readonly GroupRegistry $groups = new GroupRegistry(),
        private readonly ?BaseConnection $db = null,
    ) {
    }

    public function registry(): WidgetRegistry
    {
        return $this->widgets;
    }

    /**
     * The layout of a group: every widget with its configuration, sorted by position.
     *
     * @return array<string, array{enabled: bool, position: int, size: string, options: array<string, mixed>}>
     */
    public function groupLayout(string $group): array
    {
        if (isset($this->groupCache[$group])) {
            return $this->groupCache[$group];
        }

        $rows = [];

        foreach ($this->db()->table(self::GROUPS)->where('group_name', $group)->get()->getResultArray() as $row) {
            $rows[(string) $row['widget']] = $row;
        }

        $layout = [];
        $index  = 0;

        foreach ($this->widgets->all() as $id => $widget) {
            $index++;
            $row     = $rows[$id] ?? null;
            $stored      = $row === null ? [] : json_decode((string) ($row['options'] ?? ''), true);
            $layout[$id] = [
                'enabled'  => $row === null || (int) $row['enabled'] === 1,
                'position' => $row === null ? $index * 10 : (int) $row['position'],
                'size'     => WidgetSizes::pick($widget, $row === null ? null : (string) $row['size']),
                'options'  => $this->options->resolve($widget, is_array($stored) ? $stored : []),
            ];
        }

        return $this->groupCache[$group] = self::sorted($layout);
    }

    /**
     * Saves a group's layout (already validated: `GroupLayout` controller).
     *
     * @param array<string, array{enabled: bool, position: int, size: string, options: array<string, mixed>}> $layout
     */
    public function saveGroup(string $group, array $layout): void
    {
        $db  = $this->db();
        $now = Time::now()->format('Y-m-d H:i:s');

        $db->transException(true)->transStart();

        foreach ($layout as $id => $config) {
            $widget = $this->widgets->get($id);

            if ($widget === null) {
                continue;
            }

            $data = [
                'enabled'    => $config['enabled'] ? 1 : 0,
                'position'   => $config['position'],
                'size'       => WidgetSizes::pick($widget, $config['size']),
                'options'    => json_encode($config['options'], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ];
            $where = ['group_name' => $group, 'widget' => $id];

            if ($db->table(self::GROUPS)->where($where)->countAllResults() > 0) {
                $db->table(self::GROUPS)->where($where)->update($data);
            } else {
                $db->table(self::GROUPS)->insert($where + $data);
            }
        }

        $db->transComplete();
        unset($this->groupCache[$group]);
    }

    /**
     * The widgets a user may see, in their groups' order, with the group's size and options.
     *
     * @return array<string, array{widget: WidgetInterface, position: int, size: string, options: array<string, mixed>}>
     */
    public function available(User $user): array
    {
        $auth      = Authorization::for($user);
        $memberOf  = $user->getGroups() ?? [];
        $groups    = array_values(array_filter(array_keys($this->groups->all()), static fn (string $g): bool => in_array($g, $memberOf, true)));
        $available = [];

        foreach ($this->widgets->all() as $id => $widget) {
            $permission = $widget->permission();

            if ($permission !== null && ! $auth->can($permission)) {
                continue;
            }

            foreach ($groups as $group) {
                $config = $this->groupLayout($group)[$id] ?? null;

                if ($config !== null && $config['enabled']) {
                    $available[$id] = ['widget' => $widget, 'position' => $config['position'], 'size' => $config['size'], 'options' => $config['options']];

                    break;
                }
            }
        }

        return self::sorted($available);
    }

    /**
     * A user's dashboard: the available widgets in the user's order with their sizes and whether
     * the user hid them.
     *
     * @return list<array{id: string, widget: WidgetInterface, size: string, hidden: bool, options: array<string, mixed>}>
     */
    public function forUser(User $user): array
    {
        $available = $this->available($user);
        $result    = [];

        foreach ($this->userLayout((int) $user->id) as $entry) {
            $item = $available[$entry['id']] ?? null;

            if ($item === null) {
                continue; // no longer available: permission, group layout or module changed
            }

            $result[] = [
                'id'      => $entry['id'],
                'widget'  => $item['widget'],
                'size'    => WidgetSizes::pick($item['widget'], $entry['size']),
                'hidden'  => $entry['hidden'],
                'options' => $item['options'],
            ];
            unset($available[$entry['id']]);
        }

        foreach ($available as $id => $item) {
            $result[] = ['id' => $id, 'widget' => $item['widget'], 'size' => $item['size'], 'hidden' => false, 'options' => $item['options']];
        }

        return $result;
    }

    /**
     * Whether the user has a layout of their own.
     */
    public function hasUserLayout(User $user): bool
    {
        return $this->db()->table(self::USERS)->where('user_id', (int) $user->id)->countAllResults() > 0;
    }

    /**
     * Saves a user's layout. Entries of widgets the user cannot see are dropped, sizes are kept
     * within the widget's sizes; available widgets missing from the list keep their place at the
     * end.
     *
     * @param list<mixed> $entries Each: {id, size, hidden}
     *
     * @return int The number of widgets saved
     */
    public function saveUser(User $user, array $entries): int
    {
        $available = $this->available($user);
        $layout    = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || ! isset($available[$entry['id']]) || isset($layout[$entry['id']])) {
                continue;
            }

            $layout[$entry['id']] = [
                'id'     => $entry['id'],
                'size'   => WidgetSizes::pick($available[$entry['id']]['widget'], is_string($entry['size'] ?? null) ? $entry['size'] : null),
                'hidden' => in_array($entry['hidden'] ?? false, [true, 1, '1', 'true', 'on'], true),
            ];
        }

        $data  = ['layout' => json_encode(array_values($layout), JSON_THROW_ON_ERROR), 'updated_at' => Time::now()->format('Y-m-d H:i:s')];
        $table = $this->db()->table(self::USERS);

        if ($this->hasUserLayout($user)) {
            $table->where('user_id', (int) $user->id)->update($data);
        } else {
            $table->insert(['user_id' => (int) $user->id] + $data);
        }

        return count($layout);
    }

    /**
     * "Restore the group layout": forgets the user's own layout.
     */
    public function resetUser(User $user): void
    {
        $this->db()->table(self::USERS)->where('user_id', (int) $user->id)->delete();
    }

    /**
     * The stored layout of a user (invalid entries dropped).
     *
     * @return list<array{id: string, size: string|null, hidden: bool}>
     */
    private function userLayout(int $userId): array
    {
        $row     = $this->db()->table(self::USERS)->where('user_id', $userId)->get()->getRowArray();
        $decoded = is_array($row) ? json_decode((string) $row['layout'], true) : null;
        $layout  = [];

        foreach (is_array($decoded) ? $decoded : [] as $entry) {
            if (is_array($entry) && is_string($entry['id'] ?? null)) {
                $layout[] = [
                    'id'     => $entry['id'],
                    'size'   => is_string($entry['size'] ?? null) ? $entry['size'] : null,
                    'hidden' => ($entry['hidden'] ?? false) === true,
                ];
            }
        }

        return $layout;
    }

    /**
     * Sorted by position, keeping the manifest order for equal positions.
     *
     * @template T of array{position: int}
     *
     * @param array<string, T> $items
     *
     * @return array<string, T>
     */
    private static function sorted(array $items): array
    {
        $order = array_flip(array_keys($items));
        uksort($items, static fn (string $a, string $b): int => [$items[$a]['position'], $order[$a]] <=> [$items[$b]['position'], $order[$b]]);

        return $items;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? db_connect();
    }
}
