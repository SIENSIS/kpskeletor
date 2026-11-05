<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\FeatureTestTrait;
use Modules\Core\Filters\Can;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\GrantManager;

/**
 * The central checks: `Authorization`, the `can` filter (any/all), the `can()` helper.
 *
 * @internal
 */
final class AuthorizationTest extends RbacTestCase
{
    use FeatureTestTrait;

    public function testGuestsHoldNothing(): void
    {
        $this->assertFalse(Authorization::for(null)->can('auth-profile.view'));
        $this->assertFalse(Authorization::current()->can('auth-profile.view'));
        $this->assertFalse(can('auth-profile.view'));
    }

    public function testGroupGrantsAndDefaultDeny(): void
    {
        $user = $this->createUser('ana@example.com');

        $this->assertTrue(Authorization::for($user)->can('auth-profile.edit'));
        $this->assertFalse(Authorization::for($user)->can('modules.list'));
        $this->assertFalse(Authorization::for($user)->can());
    }

    public function testSuperadminBypassesButNotForUnknownPermissions(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');

        $this->assertTrue(Authorization::for($super)->can('modules.upload'));
        $this->assertTrue(Authorization::for($super)->can('news-posts.purge'));
        $this->assertFalse(Authorization::for($super)->can('news-posts.typo'), 'Not in the catalogue.');

        $this->setModuleEnabled('News', false);
        $this->assertFalse(Authorization::for($super)->can('news-posts.purge'), 'Disabled module.');
    }

    public function testAnyAndAll(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $auth  = Authorization::for($admin);

        $this->assertTrue($auth->can('modules.upload', 'modules.list'));
        $this->assertFalse($auth->canAll('modules.upload', 'modules.list'));
        $this->assertTrue($auth->canAll('modules.sync', 'modules.list'));
        $this->assertFalse($auth->canAll());
    }

    public function testIndividualUserPermissions(): void
    {
        $user = $this->createUser('ana@example.com');
        (new GrantManager())->grantToUser(null, $user, ['news-posts.edit']);

        $user = $this->reload($user);
        $this->assertTrue(Authorization::for($user)->canAll('news-posts.edit', 'news-posts.list', 'news-posts.show'), 'Implied permissions are added.');
        $this->assertFalse(Authorization::for($user)->can('news-posts.delete'));

        (new GrantManager())->revokeFromUser(null, $user, ['news-posts.edit']);
        $this->assertFalse(Authorization::for($this->reload($user))->can('news-posts.edit'));
    }

    public function testTheCanFilterAcceptsAnyOrAll(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $this->actAs($admin);
        $filter  = new Can();
        $request = service('request');

        $this->assertNull($filter->before($request, ['modules.upload', 'modules.list']), 'any');
        $this->assertSame(403, $filter->before($request, ['all', 'modules.upload', 'modules.list'])?->getStatusCode(), 'all');
        $this->assertNull($filter->before($request, ['all', 'modules.sync', 'modules.list']));
        $this->assertSame(403, $filter->before($request, ['all'])?->getStatusCode(), 'all without permissions');
        $this->assertSame(403, $filter->before($request, [])?->getStatusCode());
        $this->assertTrue(can('modules.list'));
        $this->assertFalse(can_all('modules.list', 'modules.upload'));
    }

    public function testABackOfficeRouteRefusesAUserWithoutThePermission(): void
    {
        $user = $this->createUser('ana@example.com');
        $user->removeGroup('user'); // no group: not even the personal screens
        $this->actAs($this->reload($user));

        $this->get('backend/profile')->assertStatus(403);
    }

    public function testGuestsOfTheBackOfficeGoToTheLogin(): void
    {
        $this->actAs(null);

        $result = $this->get('backend/profile');

        $result->assertRedirect();
        $this->assertStringContainsString('login', $result->getRedirectUrl() ?? '');
    }
}
