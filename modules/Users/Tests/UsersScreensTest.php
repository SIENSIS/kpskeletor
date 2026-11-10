<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Services\SessionTracker;
use Modules\Auth\Services\TwoFactor;

/**
 * The users' CRUD: adding accounts through Shield (email identity, default group, forced
 * password change), editing, the row operations and the escalation rules on accounts
 * (`users-users.*`, `EscalationGuard::canManageUser()`).
 *
 * @internal
 */
final class UsersScreensTest extends UsersTestCase
{
    public function testAnAdminAddsAUserThroughShield(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('GET', 'backend/users')->assertOK();
        $this->send('POST', 'backend/users/add', [
            'username' => 'newbie', 'email' => 'Newbie@Example.com',
            'password' => 'Another-Horse-Battery-77', 'password_confirm' => 'Another-Horse-Battery-77',
        ])->assertRedirect();

        $user = model(UserModel::class)->findByCredentials(['email' => 'newbie@example.com']);
        $this->assertNotNull($user, 'The email identity is created by Shield.');
        $this->assertSame('newbie', $user->username);
        $this->assertTrue($user->active);
        $this->assertSame(['user'], $user->getGroups());
        $this->assertTrue($user->requiresPasswordReset(), 'The initial password must be changed at the first login.');
        $this->assertTrue(service('passwords')->verify('Another-Horse-Battery-77', (string) $user->password_hash));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'user_created')->countAllResults());
    }

    public function testWeakPasswordsAndTakenEmailsAreRefused(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/add', ['username' => 'weak', 'email' => 'weak@example.com', 'password' => 'short', 'password_confirm' => 'short']);
        $this->send('POST', 'backend/users/add', ['username' => 'twin', 'email' => 'boss@example.com', 'password' => 'Another-Horse-Battery-77', 'password_confirm' => 'Another-Horse-Battery-77']);
        $this->send('POST', 'backend/users/add', ['username' => 'nomatch', 'email' => 'nomatch@example.com', 'password' => 'Another-Horse-Battery-77', 'password_confirm' => 'Different-Horse-Battery-77']);

        $this->assertSame(0, db_connect()->table('users')->whereIn('username', ['weak', 'twin', 'nomatch'])->countAllResults());
    }

    public function testAnAdminChangesTheEmailOfAUser(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/edit/' . $this->code($ana->id), ['username' => 'ana', 'email' => 'ana.new@example.com', 'active' => '1'])->assertRedirect();

        $this->assertSame('ana.new@example.com', $this->fresh($ana)->email);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'email_changed')->countAllResults());
    }

    public function testAnAdminCannotTouchASuperadminAccount(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $root = $this->createUser('root@example.com', 'superadmin');
        $this->login('boss@example.com');
        $code = $this->code($root->id);

        $this->send('POST', 'backend/users/edit/' . $code, ['username' => 'root', 'email' => 'mine@example.com', 'active' => '1']);
        $this->send('POST', 'backend/users/ban/' . $code);
        $this->send('POST', 'backend/users/forcereset/' . $code);
        $this->send('POST', 'backend/users/logout/' . $code);
        $this->assertNotFound('GET', 'backend/users/access/' . $root->id);

        $root = $this->fresh($root);
        $this->assertSame('root@example.com', $root->email);
        $this->assertFalse($root->isBanned());
        $this->assertFalse($root->requiresPasswordReset());
    }

    public function testAnAdminCannotManageAnAccountStrongerThanTheirOwn(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $strong = $this->createUser('strong@example.com', 'user');
        $strong->addPermission('users-users.impersonate'); // something no admin holds
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/ban/' . $this->code($strong->id));
        $this->send('POST', 'backend/users/edit/' . $this->code($strong->id), ['username' => 'strong', 'email' => 'taken@example.com', 'active' => '1']);

        $strong = $this->fresh($strong);
        $this->assertFalse($strong->isBanned());
        $this->assertSame('strong@example.com', $strong->email);
    }

    public function testNobodyBansOrDeactivatesThemselves(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/ban/' . $this->code($boss->id));
        $this->send('POST', 'backend/users/edit/' . $this->code($boss->id), ['username' => 'boss', 'email' => 'boss@example.com', 'active' => '0']);

        $boss = $this->fresh($boss);
        $this->assertFalse($boss->isBanned());
        $this->assertTrue($boss->active);
    }

    public function testABanEndsTheSessionsAndAnUnbanLiftsIt(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        (new SessionTracker())->start($ana, false);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/ban/' . $this->code($ana->id))->assertRedirect();
        $this->assertTrue($this->fresh($ana)->isBanned());
        $this->assertSame([], (new SessionTracker())->activeFor((int) $ana->id));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'user_banned')->countAllResults());
        $this->assertSame(0, db_connect()->table('activity_log')->where('action', 'ban')->countAllResults(), 'One entry per ban, written by the service.');

        $this->send('POST', 'backend/users/ban/' . $this->code($ana->id))->assertRedirect();
        $this->assertFalse($this->fresh($ana)->isBanned());
    }

    public function testForcingAResetAndLoggingOutEverywhere(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        (new SessionTracker())->start($ana, false);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/forcereset/' . $this->code($ana->id))->assertRedirect();
        $this->send('POST', 'backend/users/logout/' . $this->code($ana->id))->assertRedirect();

        $this->assertTrue($this->fresh($ana)->requiresPasswordReset());
        $this->assertSame([], (new SessionTracker())->activeFor((int) $ana->id));
    }

    public function testResettingTwoFactorNeedsARecentReauthentication(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        (new TwoFactor())->enableEmail($ana);
        $this->login('boss@example.com');

        $this->later(16 * 60);
        $this->send('POST', 'backend/users/reset2fa/' . $this->code($ana->id))->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame(TwoFactor::EMAIL, (new TwoFactor())->method((int) $ana->id));
        $this->assertSame(0, db_connect()->table('activity_log')->whereIn('action', ['reset2fa', '2fa_reset'])->countAllResults(), 'Nothing logged: the reset did not happen.');
    }

    public function testResettingTwoFactorNotifiesTheUser(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        (new TwoFactor())->enableEmail($ana);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/reset2fa/' . $this->code($ana->id))->assertRedirect();

        $this->assertNull((new TwoFactor())->method((int) $ana->id));
        $this->assertSame(1, db_connect()->table('notifications')->where('user_id', $ana->id)->countAllResults());
    }

    public function testUsersWithoutPermissionsGetNothingAndForgedPostsChangeNothing(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $bob  = $this->createUser('bob@example.com', 'user');
        $code = $this->code($bob->id);
        $this->login('ana@example.com');

        $this->send('GET', 'backend/users')->assertStatus(403);
        $this->send('POST', 'backend/users/add', ['username' => 'forged', 'email' => 'forged@example.com', 'password' => 'Another-Horse-Battery-77', 'password_confirm' => 'Another-Horse-Battery-77'])->assertStatus(403);
        $this->send('POST', 'backend/users/ban/' . $code)->assertStatus(403);
        $this->send('POST', 'backend/users/impersonate/' . $code)->assertStatus(403);
        $this->send('GET', 'backend/users/access/' . $bob->id)->assertStatus(403);
        $this->send('POST', 'backend/users/permissions', ['grants' => ['user' => ['users-users.list']]])->assertStatus(403);

        $this->assertFalse($this->fresh($bob)->isBanned());
        $this->assertSame(0, db_connect()->table('users')->where('username', 'forged')->countAllResults());
        $this->assertFalse($ana->can('users-users.list'));
    }

    public function testTheListShowsEmailsGroupsAndStatus(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'editor');
        $ana->ban();
        $this->login('boss@example.com');

        $page = $this->send('GET', 'backend/users');
        $page->assertOK();
        $page->assertSee('ana@example.com');
        $page->assertSee(lang('Users.users.statusBanned'));
        $page->assertSee('Editor');
    }

    public function testTheListFiltersByActiveBannedAndLastActivity(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $bob = $this->createUser('bob@example.com', 'user');
        $cat = $this->createUser('cat@example.com', 'user');
        $bob->ban();
        db_connect()->table('users')->where('id', $cat->id)->update(['active' => 0, 'last_active' => '2026-01-10 10:00:00']);
        db_connect()->table('users')->where('id', $ana->id)->update(['last_active' => '2026-06-10 10:00:00']);
        $this->login('boss@example.com');

        $banned = $this->send('GET', 'backend/users?f[status]=banned');
        $banned->assertSee('bob@example.com');
        $banned->assertDontSee('ana@example.com');

        $inactive = $this->send('GET', 'backend/users?f[active]=0');
        $inactive->assertSee('cat@example.com');
        $inactive->assertDontSee('ana@example.com');

        $range = $this->send('GET', 'backend/users?f[last_active][from]=2026-06-01&f[last_active][to]=2026-06-30');
        $range->assertSee('ana@example.com');
        $range->assertDontSee('cat@example.com');

        $search = $this->send('GET', 'backend/users?q=bo');
        $search->assertSee('bob@example.com');
        $search->assertDontSee('cat@example.com');
    }
}
