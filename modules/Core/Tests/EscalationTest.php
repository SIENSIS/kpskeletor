<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Events\Events;
use Modules\Core\Permissions\EscalationException;
use Modules\Core\Permissions\EscalationGuard;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;

/**
 * Grants (validation, implied permissions, atomic saves) and the escalation rules.
 *
 * @internal
 */
final class EscalationTest extends RbacTestCase
{
    public function testGrantsAreCompletedWithImpliedPermissions(): void
    {
        $stored = (new GrantManager())->setGroupGrants(null, 'editor', ['news-posts.edit', 'news-posts.purge', 'news-posts.export']);

        $this->assertSame(
            ['news-posts.edit', 'news-posts.export', 'news-posts.list', 'news-posts.purge', 'news-posts.show', 'news-posts.trash'],
            $stored,
        );
        $this->assertSame($stored, (new PermissionCatalogue())->grants('editor'));
    }

    public function testDefaultGrantsWereCompletedToo(): void
    {
        $this->assertContains('modules.list', (new PermissionCatalogue())->grants('admin'));
    }

    public function testOnlyCatalogueNamesAndMatchingWildcardsAreStored(): void
    {
        $grants = new GrantManager();
        $before = (new PermissionCatalogue())->grants('editor');

        foreach (['news-posts.typo', 'other.*', '*', 'news-*.list', 'news-posts.*.x'] as $invalid) {
            try {
                $grants->setGroupGrants(null, 'editor', ['news-posts.list', $invalid]);
                $this->fail("{$invalid} was accepted.");
            } catch (EscalationException) {
                $this->assertSame($before, (new PermissionCatalogue())->grants('editor'), 'Nothing changed.');
            }
        }

        $this->assertSame(['news-posts.*'], $grants->setGroupGrants(null, 'editor', ['news-posts.*']), 'Resource wildcards are stored as they are.');
    }

    public function testUnknownGroupsAreRefused(): void
    {
        $this->expectException(EscalationException::class);
        (new GrantManager())->setGroupGrants(null, 'teachers', ['news-posts.list']);
    }

    public function testAUserCanOnlyGrantWhatTheyHold(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $grants = new GrantManager();

        $this->assertSame(['modules.list'], $grants->setGroupGrants($admin, 'editor', ['modules.list']));

        $this->expectException(EscalationException::class);
        $grants->setGroupGrants($admin, 'editor', ['modules.upload']);
    }

    public function testAWildcardNeedsEveryPermissionItGives(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');

        $this->expectException(EscalationException::class);
        (new GrantManager())->setGroupGrants($admin, 'editor', ['modules.*']);
    }

    public function testNobodyButASuperadminChangesTheirOwnGroupsGrants(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $guard = new EscalationGuard();

        $this->assertFalse($guard->canChangeGroupGrants($admin, 'admin'));
        $this->assertFalse($guard->canChangeGroupGrants($admin, 'superadmin'));
        $this->assertTrue($guard->canChangeGroupGrants($admin, 'editor'));
        $this->assertTrue($guard->canChangeGroupGrants($this->createUser('root@example.com', 'superadmin'), 'superadmin'));

        $this->expectException(EscalationException::class);
        (new GrantManager())->setGroupGrants($admin, 'admin', ['modules.list']);
    }

    public function testNobodyChangesTheirOwnPermissions(): void
    {
        $super = $this->createUser('root@example.com', 'superadmin');

        $this->assertFalse((new EscalationGuard())->canChangeUserPermissions($super, $super));

        $this->expectException(EscalationException::class);
        (new GrantManager())->grantToUser($super, $super, ['news-posts.list']);
    }

    public function testUserPermissionsFollowTheSameRules(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $super = $this->createUser('root@example.com', 'superadmin');
        $user  = $this->createUser('ana@example.com');
        $guard = new EscalationGuard();

        $this->assertFalse($guard->canChangeUserPermissions($admin, $super), 'A superadmin\'s permissions: superadmins only.');
        $this->assertSame(['modules.list'], (new GrantManager())->grantToUser($admin, $user, ['modules.list']));

        $this->expectException(EscalationException::class);
        (new GrantManager())->grantToUser($admin, $user, ['modules.upload']);
    }

    public function testWildcardsAreNotIndividualPermissions(): void
    {
        $this->expectException(EscalationException::class);
        (new GrantManager())->grantToUser(null, $this->createUser('ana@example.com'), ['news-posts.*']);
    }

    public function testGroupMembershipRules(): void
    {
        $admin  = $this->createUser('admin@example.com', 'admin');
        $super  = $this->createUser('root@example.com', 'superadmin');
        $user   = $this->createUser('ana@example.com');
        $guard  = new EscalationGuard();

        $this->assertTrue($guard->canAddToGroup($admin, $user, 'editor'));
        $this->assertFalse($guard->canAddToGroup($admin, $user, 'superadmin'), 'Reserved for superadmins.');
        $this->assertFalse($guard->canAddToGroup($admin, $super, 'editor'), 'A superadmin\'s groups: superadmins only.');
        $this->assertFalse($guard->canRemoveFromGroup($admin, $super, 'editor'));
        $this->assertFalse($guard->canAddToGroup($admin, $admin, 'editor'), 'Not their own groups.');

        (new GrantManager())->setGroupGrants(null, 'editor', ['news-postsx.edit']);
        $this->assertFalse($guard->canAddToGroup($admin, $user, 'editor'), 'The editors hold permissions the admin lacks.');
        $this->assertTrue($guard->canAddToGroup($super, $user, 'editor'));
    }

    public function testReservedGroupsAndTheLastSuperadmin(): void
    {
        $guard = new EscalationGuard();
        $super = $this->createUser('root@example.com', 'superadmin');
        $other = $this->createUser('other@example.com', 'superadmin');

        foreach (['superadmin', 'admin', 'editor', 'user', ' Admin '] as $reserved) {
            $this->assertFalse($guard->canRenameOrDelete($reserved), $reserved);
        }

        $this->assertTrue($guard->canRenameOrDelete('teachers'));
        $this->assertTrue($guard->canRemoveFromGroup($super, $other, 'superadmin'));

        $other->removeGroup('superadmin');
        $this->assertSame(1, $guard->superadminCount());
        $this->assertFalse($guard->canRemoveFromGroup($this->reload($other), $super, 'superadmin'), 'The last superadmin keeps the group.');
    }

    public function testMembersOfAChangedGroupGetThePrivilegesChangedEvent(): void
    {
        $editor = $this->createUser('editor@example.com', 'editor');
        $this->createUser('ana@example.com');
        $changed = [];
        Events::on('privilegesChanged', static function (int $userId) use (&$changed): void {
            $changed[] = $userId;
        });

        (new GrantManager())->setGroupGrants(null, 'editor', ['news-posts.list']);

        $this->assertSame([(int) $editor->id], $changed);
    }
}
