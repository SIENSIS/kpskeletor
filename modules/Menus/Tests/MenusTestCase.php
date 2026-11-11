<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * Base of the Menus feature tests: the Auth fixture (every module migrated, catalogue and default
 * grants synced, requests with session and CSRF), the routes loaded, and helpers that write
 * menus, items and customisations directly and read the back-office menu of a user.
 */
abstract class MenusTestCase extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $this->storeDeclarations();
        (new CacheInvalidator())->invalidate(['menus', 'modules']);
    }

    /**
     * The declarations of every module (menu entries, link types…) as `modules:sync` stores them.
     */
    protected function storeDeclarations(): void
    {
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
    }

    protected function menu(string $name = 'Principal', ?string $location = 'primary'): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('menus')->insert(['name' => $name, 'location' => $location, 'created_at' => $now, 'updated_at' => $now]);

        return (int) db_connect()->insertID();
    }

    /**
     * @param array<string, mixed> $values columns of `menu_items`
     * @param list<string>         $groups group names (visibility `groups`)
     */
    protected function item(int $menu, string $label, string $type = 'url', string $value = '/', array $values = [], array $groups = []): int
    {
        $db = db_connect();
        $db->table('menu_items')->insert($values + [
            'menu_id' => $menu, 'parent_id' => null, 'position' => 0, 'type' => $type, 'value' => $value,
            'icon' => '', 'target' => '_self', 'css_class' => '', 'visibility' => 'all',
        ]);
        $id = (int) $db->insertID();
        $db->table('menu_item_translations')->insert(['item_id' => $id, 'locale' => 'ca', 'label' => $label]);
        $db->table('menu_item_translations')->insert(['item_id' => $id, 'locale' => 'en', 'label' => $label . ' (en)']);

        foreach ($groups as $group) {
            $row = $db->table('groups')->where('name', $group)->get()->getRowArray();
            $this->assertIsArray($row);
            $db->table('menu_item_groups')->insert(['item_id' => $id, 'group_id' => (int) $row['id']]);
        }

        (new CacheInvalidator())->invalidate(['menus']);

        return $id;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function customise(string $key, ?string $group, array $values): void
    {
        db_connect()->table('backend_menu_customisations')->insert($values + ['entry_key' => $key, 'group_name' => $group, 'updated_at' => Time::now()->toDateTimeString()]);
        (new CacheInvalidator())->invalidate(['menus']);
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function customLink(array $values): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('backend_menu_links')->insert($values + [
            'kind' => 'link', 'type' => 'url', 'value' => '/', 'labels' => json_encode(['ca' => 'Enllaç']), 'icon' => '', 'target' => '_self',
            'permission' => '', 'groups' => '[]', 'parent' => null, 'zone' => 'sidebar', 'position' => 100, 'created_at' => $now, 'updated_at' => $now,
        ]);
        (new CacheInvalidator())->invalidate(['menus']);

        return (int) db_connect()->insertID();
    }

    /**
     * The back-office menu of a user: zone => list of root entries.
     *
     * @return array<string, list<MenuEntry>>
     */
    protected function backendMenu(User $user): array
    {
        return (new MenuBuilder())->tree($user);
    }

    /**
     * Where an entry is: "<zone>" or "<zone>/<parent key>/…", null when the user does not see it.
     */
    protected function placeOf(User $user, string $key): ?string
    {
        $find = static function (array $entries, string $path) use (&$find, $key): ?string {
            foreach ($entries as $entry) {
                if ($entry->key === $key) {
                    return $path;
                }

                $found = $find($entry->children, $path . '/' . $entry->key);

                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        foreach ($this->backendMenu($user) as $zone => $entries) {
            $found = $find($entries, $zone);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    protected function entry(User $user, string $key): ?MenuEntry
    {
        $find = static function (array $entries) use (&$find, $key): ?MenuEntry {
            foreach ($entries as $entry) {
                if ($entry->key === $key) {
                    return $entry;
                }

                $found = $find($entry->children);

                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        foreach ($this->backendMenu($user) as $entries) {
            $found = $find($entries);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** A flash message of the last request. */
    protected function flash(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Logs in a member of a group (created on the fly) and returns them.
     */
    protected function loginAs(string $group, string $email = 'boss@example.com'): User
    {
        $user = $this->createUser($email, $group);
        $this->login($email);

        return $user;
    }
}
