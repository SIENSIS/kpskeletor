<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use Modules\Core\Config\ModuleManifest;
use Modules\Core\Menus\MenuDefinitions;
use Modules\Core\Menus\MenuEntry;
use Modules\Core\Modules\ManifestValidator;
use Modules\Menus\Services\BackendLayout;

/**
 * The settings zone grouped in the Core's five containers (D-38-01): the groups and their
 * children, per permission, and customisations saved before the groups still applied.
 *
 * @internal
 */
final class SettingsGroupsTest extends MenusTestCase
{
    /** Group key => children keys, in menu order. */
    private const GROUPS = [
        'group.access'        => ['Users.users', 'Users.groups', 'users-matrix'],
        'group.security'      => ['settings-security', 'security-status', 'Activity.log'],
        'group.api'           => ['Api.api', 'api-docs'],
        'group.customisation' => ['dashboard-groups', 'Menus.backend', 'Core.modules'],
        'group.system'        => ['settings', 'Core.schedule', 'Backup.backups', 'updates'],
    ];

    public function testTheSettingsZoneIsTheFiveGroups(): void
    {
        $superadmin = $this->createUser('root@example.com', 'superadmin');
        $zone       = $this->backendMenu($superadmin)['settings'] ?? [];

        $this->assertSame(array_keys(self::GROUPS), $this->keys($zone));

        foreach ($zone as $group) {
            $this->assertNull($group->url(), "{$group->key} is a heading without a link.");
            $this->assertSame(self::GROUPS[$group->key], $this->keys($group->children), "Children of {$group->key}.");
        }

        $this->assertSame(lang('Core.menu.groupAccess'), $zone[0]->text());
    }

    public function testOnlyTheGroupsWithVisibleChildrenAreShown(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->assertSame([], $this->keys($this->backendMenu($user)['settings'] ?? []), 'Nothing in the settings zone: no group.');

        $user->addPermission('users-users.list');
        $zone = $this->backendMenu($user)['settings'] ?? [];

        $this->assertSame(['group.access'], $this->keys($zone));
        $this->assertSame(['Users.users'], $this->keys($zone[0]->children));
    }

    public function testCustomisationsSavedBeforeTheGroupsStillApply(): void
    {
        // Moved to the sidebar root, renamed, and hidden: stored by entry key, which did not change.
        $this->customise('Backup.backups', null, ['parent' => '', 'zone' => 'sidebar', 'position' => 90]);
        $this->customise('Users.groups', null, ['labels' => json_encode(['ca' => 'Rols', 'en' => 'Roles'])]);
        $this->customise('updates', null, ['hidden' => 1]);
        // A group moved and retouched like any other entry.
        $this->customise('group.api', null, ['zone' => 'sidebar', 'position' => 80]);

        $superadmin = $this->createUser('root@example.com', 'superadmin');

        $this->assertSame('sidebar', $this->placeOf($superadmin, 'Backup.backups'));
        $this->assertSame('Roles', $this->entry($superadmin, 'Users.groups')?->text());
        $this->assertSame('settings/group.access', $this->placeOf($superadmin, 'Users.groups'));
        $this->assertNull($this->placeOf($superadmin, 'updates'));
        $this->assertSame('settings/group.system', $this->placeOf($superadmin, 'settings'));
        $this->assertSame('sidebar/group.api', $this->placeOf($superadmin, 'api-docs'));
    }

    public function testTheBackOfficeMenuEditorShowsTheGroups(): void
    {
        $tree   = (new BackendLayout())->tree(null);
        $groups = array_values(array_filter($tree['settings'], static fn (array $node): bool => $node['kind'] === 'group'));

        $this->assertSame(array_keys(self::GROUPS), array_column($groups, 'key'));
        $this->assertSame('', $groups[0]['permission']);
        $this->assertSame(['Users.users', 'Users.groups', 'users-matrix'], array_column($groups[0]['children'], 'key'));

        $this->loginAs('admin');
        $html = (string) $this->send('GET', 'backend/menus/backend')->response()->getBody();
        $this->assertStringContainsString('data-key="group.system"', $html);
        $this->assertStringContainsString(esc(lang('Core.menu.groupAccess')), $html);
    }

    public function testGroupEntriesInAManifest(): void
    {
        $manifest = new class () extends ModuleManifest {
            public string $name = 'Shop';

            /** @var list<array<string, mixed>> */
            public array $menu = [
                ['group' => 'group.shop', 'label' => 'Shop.menu.group', 'zone' => 'settings', 'order' => 90],
                ['route' => 'shop-settings', 'permission' => 'shop-settings.view', 'label' => 'Shop.menu.settings', 'zone' => 'settings', 'parent' => MenuDefinitions::GROUP_SYSTEM],
                ['route' => 'shop-other', 'permission' => 'shop-other.view', 'label' => 'Shop.menu.other', 'zone' => 'settings', 'parent' => 'group.unknown'],
            ];
        };

        $definitions = MenuDefinitions::of($manifest);
        $this->assertSame('group', $definitions['group.shop']['kind'] ?? null);
        $this->assertSame('', $definitions['group.shop']['route']);
        $this->assertSame('group.system', $definitions['shop-settings']['parent']);
        $this->assertSame([], (new ManifestValidator())->validate($manifest, '/modules/Shop'), 'A Core group (or an unknown one) is a valid parent.');

        $bad = new class () extends ModuleManifest {
            public string $name = 'Shop';

            /** @var list<array<string, mixed>> */
            public array $menu = [
                ['group' => 'Shop group', 'label' => 'Shop.menu.group'],
                ['group' => 'group.shop', 'route' => 'shop', 'label' => 'Shop.menu.group'],
            ];
        };

        $errors = implode("\n", (new ManifestValidator())->validate($bad, '/modules/Shop'));
        $this->assertStringContainsString("\$menu[0]['group']", $errors);
        $this->assertStringContainsString("\$menu[1]['route']", $errors);
        $this->assertArrayNotHasKey('Shop group', MenuDefinitions::of($bad));
    }

    /**
     * @param list<MenuEntry> $entries
     *
     * @return list<string>
     */
    private function keys(array $entries): array
    {
        return array_map(static fn (MenuEntry $e): string => $e->key, $entries);
    }
}
