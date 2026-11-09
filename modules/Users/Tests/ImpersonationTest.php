<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use Modules\Auth\Services\Impersonation;

/**
 * Impersonation: superadmins only, never another superadmin, after a re-authentication, limited
 * in time, with a banner and a way back; sensitive actions refused meanwhile; start and end
 * logged (docs/specification.md, "Modules" › "Users").
 *
 * @internal
 */
final class ImpersonationTest extends UsersTestCase
{
    public function testASuperadminImpersonatesAUserAndComesBack(): void
    {
        $root = $this->createUser('root@example.com', 'superadmin');
        $ana  = $this->createUser('ana@example.com', 'user');
        $this->login('root@example.com');

        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id))->assertRedirectTo(site_url('backend/profile'));
        $this->assertSame((int) $ana->id, $this->loggedInUserId());
        $this->assertTrue(Impersonation::active());

        $page = $this->send('GET', 'backend/profile');
        $page->assertOK();
        $page->assertSee(lang('Auth.impersonation.banner', ['ana']));
        $page->assertSee('auth/impersonation/stop');

        $this->send('POST', 'auth/impersonation/stop')->assertRedirectTo(site_url('backend/users'));
        $this->assertSame((int) $root->id, $this->loggedInUserId());
        $this->assertFalse(Impersonation::active());

        $actions = array_column(db_connect()->table('activity_log')->where('module', 'Auth')->like('action', 'impersonation', 'after')->orderBy('id')->get()->getResultArray(), 'action');
        $this->assertSame(['impersonation_started', 'impersonation_ended'], $actions);
    }

    public function testAnAdminCannotImpersonate(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));

        $this->assertNotSame((int) $ana->id, $this->loggedInUserId());
        $this->assertFalse(Impersonation::active());
    }

    public function testASuperadminCannotImpersonateAnotherSuperadmin(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $other = $this->createUser('other@example.com', 'superadmin');
        $this->login('root@example.com');

        $this->send('POST', 'backend/users/impersonate/' . $this->code($other->id));

        $this->assertNotSame((int) $other->id, $this->loggedInUserId());
        $this->assertFalse(Impersonation::active());
    }

    public function testBannedUsersCannotBeImpersonated(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $ana = $this->createUser('ana@example.com', 'user');
        $ana->ban();
        $this->login('root@example.com');

        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));

        $this->assertFalse(Impersonation::active());
    }

    public function testImpersonatingNeedsARecentReauthentication(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('root@example.com');
        $this->later(16 * 60);

        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id))->assertRedirectTo(site_url('auth/reauth'));
        $this->assertFalse(Impersonation::active());
    }

    public function testSensitiveActionsAreRefusedWhileImpersonating(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $ana = $this->createUser('ana@example.com', 'admin');
        $this->login('root@example.com');
        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));
        $this->assertSame((int) $ana->id, $this->loggedInUserId());

        // Re-authentication, the reauth-protected routes, password and email changes.
        $this->send('GET', 'auth/reauth')->assertRedirectTo(site_url('backend/profile'));
        $this->send('POST', 'auth/reauth', ['password' => self::$password])->assertRedirectTo(site_url('backend/profile'));
        $this->send('POST', 'backend/profile/two-factor/email')->assertStatus(403);
        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'Sneaky', 'scope' => 'all', 'days' => '30'])->assertStatus(403);
        $this->send('POST', 'backend/users/permissions', ['grants' => ['editor' => ['users-users.list']]])->assertStatus(403);
        $this->send('POST', 'backend/profile/password', ['current_password' => self::$password, 'password' => 'Another-Horse-Battery-77', 'password_confirm' => 'Another-Horse-Battery-77'])
            ->assertRedirectTo(site_url('backend/profile'));

        $this->assertTrue(service('passwords')->verify(self::$password, (string) $this->fresh($ana)->password_hash));
        $this->assertSame([], db_connect()->table('auth_identities')->where('user_id', $ana->id)->where('type', 'access_token')->get()->getResultArray());
        $this->assertGreaterThan(0, db_connect()->table('activity_log')->where('action', 'impersonation_blocked')->countAllResults());
    }

    public function testTheImpersonationEndsWhenItsTimeIsOver(): void
    {
        $root = $this->createUser('root@example.com', 'superadmin');
        $ana  = $this->createUser('ana@example.com', 'user');
        $this->login('root@example.com');
        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));

        $this->later(29 * 60);
        $this->send('GET', 'backend/profile')->assertOK();
        $this->assertSame((int) $ana->id, $this->loggedInUserId());

        $this->later(31 * 60);
        // The pending request is not run as anybody: back to the impersonator's profile.
        $this->send('POST', 'backend/profile', ['name' => 'Changed'])->assertRedirectTo(site_url('backend/profile'));
        $this->assertSame((int) $root->id, $this->loggedInUserId());
        $this->assertFalse(Impersonation::active());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'impersonation_ended')->where('summary', Impersonation::END_EXPIRED)->countAllResults());
    }

    public function testTheTimeLimitIsASetting(): void
    {
        setting('Users.impersonationMinutes', 5);
        $this->createUser('root@example.com', 'superadmin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('root@example.com');
        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));

        $this->assertSame(self::NOW + 5 * 60, Impersonation::state()['expires'] ?? null);
    }

    public function testLoggingOutWhileImpersonatingEndsBoth(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('root@example.com');
        $this->send('POST', 'backend/users/impersonate/' . $this->code($ana->id));

        $this->send('POST', 'logout')->assertRedirect();

        $this->assertNull($this->loggedInUserId());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'impersonation_ended')->where('summary', Impersonation::END_LOGOUT)->countAllResults());
    }

    public function testStoppingWithoutAnImpersonationDoesNothing(): void
    {
        $root = $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        $this->send('POST', 'auth/impersonation/stop')->assertRedirectTo(site_url('login'));
        $this->assertSame((int) $root->id, $this->loggedInUserId());
    }
}
