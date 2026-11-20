<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The menu entry in its own settings group `group.development` (D-41-04): the group is a heading
 * shown only while its entry is visible, so only a superadmin sees it, and only where the routes
 * exist (development, local request).
 *
 * @internal
 */
final class DevtoolsMenuTest extends DevtoolsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CacheInvalidator())->invalidate(['menus', 'modules']);
    }

    public function testTheGroupIsShownToTheSuperadminWhereTheRoutesExist(): void
    {
        $this->withDevtoolsRoutes();
        $group = $this->group($this->createUser('root@example.com', 'superadmin'));

        $this->assertNotNull($group);
        $this->assertNull($group->url(), 'A heading without a link.');
        $this->assertSame(['Devtools.tools'], array_map(static fn (MenuEntry $e): string => $e->key, $group->children));
        $this->assertSame(lang('Devtools.menu.group'), $group->text());
    }

    public function testNobodyElseSeesAnEmptyGroup(): void
    {
        $this->withDevtoolsRoutes();

        foreach (['admin', 'editor', 'user'] as $groupName) {
            $this->assertNull($this->group($this->createUser($groupName . '@example.com', $groupName)), $groupName);
        }
    }

    public function testWithoutTheRoutesNotEvenTheSuperadminSeesIt(): void
    {
        // The suite runs in `testing`: Config/Routes.php registered nothing.
        service('routes')->loadRoutes();

        $this->assertNull($this->group($this->createUser('root@example.com', 'superadmin')));
    }

    private function group(User $user): ?MenuEntry
    {
        foreach ((new MenuBuilder())->tree($user)['settings'] ?? [] as $entry) {
            if ($entry->key === 'group.development') {
                return $entry;
            }
        }

        return null;
    }
}
