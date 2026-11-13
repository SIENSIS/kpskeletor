<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Shield's `AuthGroups.permissions`/`matrix` are computed from the catalogue and
 * `group_permissions` and kept in agreement with them.
 *
 * @internal
 */
final class ShieldMatrixTest extends RbacTestCase
{
    public function testTheConfigFileHoldsNoPermissions(): void
    {
        $config = new \Config\AuthGroups();

        $this->assertSame([], $config->permissions, 'Default deny: Shield gets its permissions from the settings.');
        $this->assertSame([], $config->matrix);
    }

    public function testSettingsAgreeWithTheGrantsTable(): void
    {
        $catalogue = new PermissionCatalogue();
        $matrix    = (array) setting('AuthGroups.matrix');

        $this->assertTrue((new ShieldMatrix())->inSync());
        $this->assertSame($catalogue->enabledNames(), array_keys((array) setting('AuthGroups.permissions')));

        foreach (['superadmin', 'admin', 'editor', 'user'] as $group) {
            $this->assertSame($catalogue->grants($group), $matrix[$group], $group);
        }

        $this->assertContains('auth-profile.*', $matrix['user'], 'Personal screens are granted to every group (D-00-17).');
        $this->assertSame(['activity-log.*', 'activity-mine.*', 'api.*', 'auth-profile.*', 'auth-sessions.*', 'core-notifications.*', 'dashboard.*', 'events-calendar.*', 'events-categories.*', 'events-events.*', 'files.*', 'menus-backend.*', 'menus-public.*', 'modules.list', 'modules.sync', 'modules.toggle', 'news-api.*', 'news-categories.*', 'news-mine.*', 'news-posts.*', 'news-read.*', 'pages.*', 'schedule.*', 'settings.*',
            'users-groups.*', 'users-matrix.*', 'users-users.access', 'users-users.add', 'users-users.ban', 'users-users.edit', 'users-users.forcereset',
            'users-users.list', 'users-users.logout', 'users-users.reset2fa', 'users-users.show'], $matrix['admin']);
    }

    public function testAGrantChangeRewritesTheMatrix(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['auth-profile.*', 'auth-sessions.*', 'news-posts.edit']);

        $this->assertContains('news-posts.edit', setting('AuthGroups.matrix')['editor']);
        $this->assertTrue((new ShieldMatrix())->inSync());
    }

    public function testAStaleMatrixIsDetected(): void
    {
        db_connect()->table('group_permissions')->insert(['group' => 'user', 'permission' => 'modules.list', 'created_at' => '2026-11-05 10:00:00']);

        $this->assertFalse((new ShieldMatrix())->inSync());
    }

    public function testADisabledModuleLeavesTheMatrixAndComesBack(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['news-posts.*', 'news-categories.list']);
        $editor = $this->createUser('editor@example.com', 'editor');
        $this->assertTrue(Authorization::for($editor)->can('news-posts.publish'));

        $this->setModuleEnabled('News', false);

        $this->assertNotContains('news-posts.*', setting('AuthGroups.matrix')['editor']);
        $this->assertNotContains('news-posts.edit', array_keys(setting('AuthGroups.permissions')));
        $this->assertFalse(Authorization::for($this->reload($editor))->can('news-posts.publish'));
        $this->assertSame(['news-categories.list', 'news-posts.*'], (new PermissionCatalogue())->grants('editor'), 'Grants are kept.');

        $this->setModuleEnabled('News', true);
        $this->assertTrue(Authorization::for($this->reload($editor))->can('news-posts.publish'));
    }

    public function testAResourceWildcardDoesNotMatchALongerPrefix(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['news-posts.*']);
        $editor = $this->createUser('editor@example.com', 'editor');

        $this->assertTrue(Authorization::for($editor)->can('news-posts.edit'));
        $this->assertFalse(Authorization::for($editor)->can('news-postsx.edit'));
        $this->assertSame(['news-posts.add'], ShieldMatrix::matching('news-posts.*', ['news-posts.add', 'news-postsx.edit']));
    }
}
