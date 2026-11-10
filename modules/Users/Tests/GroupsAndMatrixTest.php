<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Groups (Core `groups` table through kpcrudy), the permission matrix and a user's access:
 * reserved names, common grants of new groups, 2FA policy, re-authentication, atomic saves of
 * atomic names and wildcards only, implied permissions and the escalation rules.
 *
 * @internal
 */
final class GroupsAndMatrixTest extends UsersTestCase
{
    public function testANewGroupIsMirroredAndGetsThePersonalScreens(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/groups/add', ['name' => 'teachers', 'title' => 'Teachers', 'description' => '', 'two_factor' => 'required'])->assertRedirect();

        $this->assertArrayHasKey('teachers', (array) setting('AuthGroups.groups'));
        $this->assertSame('required', GroupRegistry::twoFactorPolicy()['teachers']);
        $grants = (new PermissionCatalogue())->grants('teachers');
        $this->assertContains('auth-profile.*', $grants);
        $this->assertContains('core-notifications.*', $grants);
        $this->assertNotContains('users-users.list', $grants);
        $this->assertTrue((new ShieldMatrix())->inSync());
    }

    public function testReservedAndInvalidNamesAreRefused(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        foreach (['admin', 'Teachers', 'a', '1group', 'x y'] as $name) {
            $this->send('POST', 'backend/users/groups/add', ['name' => $name, 'title' => 'T', 'two_factor' => 'optional']);
        }

        $this->assertSame(['superadmin', 'admin', 'editor', 'user'], array_keys((new GroupRegistry())->all()));
    }

    public function testReservedGroupsAndGroupsWithMembersCannotBeDeleted(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->send('POST', 'backend/users/groups/add', ['name' => 'teachers', 'title' => 'Teachers', 'two_factor' => 'optional']);
        $registry = new GroupRegistry();
        $teachers = $this->code($registry->find('teachers')['id'] ?? 0, 'groups');

        $this->send('POST', 'backend/users/groups/delete/' . $this->code($registry->find('editor')['id'] ?? 0, 'groups'));
        $this->assertNotNull($registry->find('editor'));

        $member = $this->createUser('teacher@example.com', 'user');
        $member->addGroup('teachers');
        $this->send('POST', 'backend/users/groups/delete/' . $teachers);
        $this->assertNotNull($registry->find('teachers'));

        $member->removeGroup('teachers');
        $this->send('POST', 'backend/users/groups/delete/' . $teachers)->assertRedirect();
        $this->assertNull($registry->find('teachers'));
        $this->assertArrayNotHasKey('teachers', (array) setting('AuthGroups.groups'));
        $this->assertSame([], (new PermissionCatalogue())->grants('teachers'));
    }

    public function testTheGroupPolicyForcesTwoFactorForItsMembers(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $editor = (new GroupRegistry())->find('editor');

        $this->send('POST', 'backend/users/groups/edit/' . $this->code($editor['id'] ?? 0, 'groups'), ['title' => 'Editor', 'description' => '', 'two_factor' => 'required'])->assertRedirect();
        $this->assertSame('required', GroupRegistry::twoFactorPolicy()['editor']);

        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('backend/profile/two-factor'));
    }

    public function testGroupChangesNeedARecentReauthentication(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->later(16 * 60);

        $this->send('GET', 'backend/users/groups')->assertOK();
        $this->send('GET', 'backend/users/groups/add')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/users/groups/add', ['name' => 'teachers', 'title' => 'Teachers', 'two_factor' => 'optional'])->assertRedirectTo(site_url('auth/reauth'));
        $this->assertNull((new GroupRegistry())->find('teachers'));

        $editor = $this->code((new GroupRegistry())->find('editor')['id'] ?? 0, 'groups');
        $this->send('GET', 'backend/users/groups/edit/' . $editor)->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/users/groups/edit/' . $editor, ['title' => 'Changed', 'description' => '', 'two_factor' => 'required'])->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/users/groups/delete/' . $editor)->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame('optional', GroupRegistry::twoFactorPolicy()['editor'] ?? 'optional');
        $this->assertNotNull((new GroupRegistry())->find('editor'));
    }

    public function testOnlyASuperadminEditsTheSuperadminGroup(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $group = (new GroupRegistry())->find('superadmin');

        $this->send('POST', 'backend/users/groups/edit/' . $this->code($group['id'] ?? 0, 'groups'), ['title' => 'Owned', 'description' => '', 'two_factor' => 'optional']);

        $this->assertSame('Superadmin', (new GroupRegistry())->find('superadmin')['title'] ?? null);
    }

    public function testTheMatrixSavesAtomicNamesAndWildcardsWithImpliedPermissions(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $catalogue = new PermissionCatalogue();
        $before    = $catalogue->grants('editor');

        $page = $this->send('GET', 'backend/users/permissions');
        $page->assertOK();
        $page->assertSee('name="grants[editor][]"');
        $page->assertDontSee('name="grants[admin][]"'); // their own group is read-only

        $this->send('POST', 'backend/users/permissions', ['grants' => [
            'editor' => [...$before, 'users-users.edit', 'activity-log.*'],
            'user'   => $catalogue->grants('user'),
        ]])->assertRedirectTo(site_url('backend/users/permissions'));

        $after = $catalogue->grants('editor');
        $this->assertContains('users-users.edit', $after);
        $this->assertContains('users-users.list', $after, 'edit implies list…');
        $this->assertContains('users-users.show', $after, '…and show.');
        $this->assertContains('activity-log.*', $after);
        $this->assertNotContains('activity-log.list', $after, 'A wildcard is stored as it is.');
        $this->assertTrue((new ShieldMatrix())->inSync());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'grants_changed')->countAllResults());
    }

    public function testTheMatrixRefusesNamesOutsideTheScreenAndSavesNothing(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $catalogue = new PermissionCatalogue();
        $before    = $catalogue->allGrants();

        foreach (['*', 'users-*', 'users-users.nonsense', 'superpower.list'] as $forged) {
            $this->send('POST', 'backend/users/permissions', ['grants' => [
                'user'   => [...$catalogue->grants('user'), 'users-users.list'],
                'editor' => [...$catalogue->grants('editor'), $forged],
            ]]);
        }

        $this->assertSame($before, $catalogue->allGrants(), 'Atomic: the valid group is not saved either.');
    }

    public function testAnAdminCannotGrantWhatTheyDoNotHold(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $catalogue = new PermissionCatalogue();
        $before    = $catalogue->allGrants();

        $this->send('POST', 'backend/users/permissions', ['grants' => [
            'user'   => [...$catalogue->grants('user'), 'users-users.list'],
            'editor' => [...$catalogue->grants('editor'), 'users-users.impersonate'],
        ]]);
        $this->send('POST', 'backend/users/permissions', ['grants' => ['editor' => [...$catalogue->grants('editor'), 'users-users.*']]]);
        $this->send('POST', 'backend/users/permissions', ['grants' => ['admin' => ['users-users.*'], 'superadmin' => []]]);

        $this->assertSame($before, $catalogue->allGrants());
    }

    public function testSavingTheMatrixNeedsReauthenticationAndTheEditPermission(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->later(16 * 60);
        $before = (new PermissionCatalogue())->allGrants();

        $this->send('GET', 'backend/users/permissions')->assertOK();
        $this->send('POST', 'backend/users/permissions', ['grants' => ['editor' => []]])->assertRedirectTo(site_url('auth/reauth'));

        $viewer = $this->createUser('viewer@example.com', 'user');
        $viewer->addPermission('users-matrix.view');
        $this->login('viewer@example.com');
        $this->send('GET', 'backend/users/permissions')->assertDontSee('name="grants[');
        $this->send('POST', 'backend/users/permissions', ['grants' => ['editor' => []]])->assertStatus(403);

        $this->assertSame($before, (new PermissionCatalogue())->allGrants());
    }

    public function testAnAdminChangesTheGroupsAndPermissionsOfAUser(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');

        $this->send('GET', 'backend/users/access/' . $ana->id)->assertOK();
        $this->send('POST', 'backend/users/access/' . $ana->id, ['groups' => ['user', 'editor'], 'permissions' => ['activity-log.list']])
            ->assertRedirectTo(site_url('backend/users/access/' . $ana->id));

        $ana = $this->fresh($ana);
        $this->assertEqualsCanonicalizing(['user', 'editor'], $ana->getGroups());
        $this->assertSame(['activity-log.list'], $ana->getPermissions());

        // Superadmin is never assigned by an admin; impersonate is not theirs to give.
        $this->send('POST', 'backend/users/access/' . $ana->id, ['groups' => ['user', 'editor', 'superadmin'], 'permissions' => ['activity-log.list']]);
        $this->send('POST', 'backend/users/access/' . $ana->id, ['groups' => ['user', 'editor'], 'permissions' => ['activity-log.list', 'users-users.impersonate']]);

        $ana = $this->fresh($ana);
        $this->assertFalse($ana->inGroup('superadmin'));
        $this->assertSame(['activity-log.list'], $ana->getPermissions());
    }

    public function testNobodyChangesTheirOwnAccessAndTheLastSuperadminKeepsTheGroup(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->assertNotFound('GET', 'backend/users/access/' . $boss->id);
        $this->assertNotFound('POST', 'backend/users/access/' . $boss->id);

        $root = $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        $this->assertNotFound('POST', 'backend/users/access/' . $root->id);
        $this->assertTrue($this->fresh($root)->inGroup('superadmin'));
    }

    public function testChangingAccessNeedsAReauthentication(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');
        $this->later(16 * 60);

        $this->send('POST', 'backend/users/access/' . $ana->id, ['groups' => ['user', 'editor']])->assertRedirectTo(site_url('auth/reauth'));
        $this->assertFalse($this->fresh($ana)->inGroup('editor'));
    }
}
