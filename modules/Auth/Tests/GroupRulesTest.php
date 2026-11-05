<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Auth\Exceptions\GroupRuleException;
use Modules\Auth\Models\UserSessionModel;
use Modules\Auth\Services\GroupRules;
use Modules\Core\Filters\Can;

/**
 * The four groups and the superadmin rules.
 *
 * @internal
 */
final class GroupRulesTest extends AuthTestCase
{
    public function testTheFourGroupsExistAndUserIsTheDefault(): void
    {
        $this->assertSame(['superadmin', 'admin', 'editor', 'user'], array_keys(setting('AuthGroups.groups')));
        $this->assertSame('user', setting('AuthGroups.defaultGroup'));
    }

    public function testReservedGroupsCannotBeRenamedOrDeleted(): void
    {
        $rules = new GroupRules();

        foreach (GroupRules::RESERVED as $group) {
            $this->assertFalse($rules->canRenameOrDelete($group), $group);
        }

        $this->assertTrue($rules->canRenameOrDelete('teachers'));
    }

    public function testOnlyASuperadminAssignsSuperadmin(): void
    {
        $rules = new GroupRules();
        $super = $this->createUser('super@example.com', 'superadmin');
        $admin = $this->createUser('admin@example.com', 'admin');
        $user  = $this->createUser('ana@example.com');

        $this->assertFalse($rules->canAssign($admin, $user, 'superadmin'));
        $this->assertTrue($rules->canAssign($admin, $user, 'editor'));
        $this->assertTrue($rules->canAssign($super, $user, 'superadmin'));
        $this->assertFalse($rules->canAssign($super, $user, 'unknown-group'));

        $this->expectException(GroupRuleException::class);
        $rules->assign($admin, $user, 'superadmin');
    }

    public function testNobodyChangesTheirOwnGroups(): void
    {
        $rules = new GroupRules();
        $super = $this->createUser('super@example.com', 'superadmin');

        $this->assertFalse($rules->canAssign($super, $super, 'admin'));
        $this->assertFalse($rules->canRemove($super, $super, 'superadmin'));
    }

    public function testTheLastSuperadminCannotLoseTheGroup(): void
    {
        $rules  = new GroupRules();
        $first  = $this->createUser('one@example.com', 'superadmin');
        $second = $this->createUser('two@example.com', 'superadmin');

        $this->assertTrue($rules->canRemove($first, $second, 'superadmin'));
        $rules->remove($first, $second, 'superadmin');

        $third = $this->createUser('three@example.com', 'admin');
        $this->assertFalse($rules->canRemove($third, $first, 'superadmin'), 'Not a superadmin, and the last one.');
        $this->assertSame(1, $rules->superadminCount());
    }

    public function testAGroupChangeFlagsTheUsersSessionsForRegeneration(): void
    {
        $super = $this->createUser('super@example.com', 'superadmin');
        $user  = $this->createUser('ana@example.com');
        $this->login('ana@example.com');
        $_SESSION = [];

        (new GroupRules())->assign($super, $user, 'editor');

        $this->assertTrue($user->inGroup('editor'));
        $this->assertSame(1, (int) model(UserSessionModel::class)->where('user_id', $user->id)->first()['regenerate']);
    }

    public function testSuperadminBypassesTheCanFilter(): void
    {
        $this->createUser('super@example.com', 'superadmin');
        $this->createUser('ana@example.com');

        $this->login('ana@example.com');
        $this->assertNotNull((new Can())->before(service('request'), ['news-posts.delete']), 'A user without the permission is refused.');
        $this->assertNull((new Can())->before(service('request'), ['auth-profile.view']), 'Personal screens are granted to every group.');

        $this->send('POST', 'logout');
        $this->login('super@example.com');
        $this->assertNull((new Can())->before(service('request'), ['news-posts.delete']));
    }
}
