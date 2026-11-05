<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\I18n\Time;
use Modules\Auth\Filters\AuthSession;
use Modules\Auth\Models\UserSessionModel;
use Modules\Auth\Services\SessionTracker;

/**
 * Absolute lifetime (with and without remember-me), the remember-me cap, remote logout,
 * privilege changes and the idle lock.
 *
 * @internal
 */
final class SessionLifetimeTest extends AuthTestCase
{
    private function ageSession(int $userId, int $minutes): void
    {
        model(UserSessionModel::class)->where('user_id', $userId)->where('ended_at', null)
            ->set(['created_at' => Time::now()->subMinutes($minutes)->format('Y-m-d H:i:s')])->update();
    }

    private function ageRememberToken(int $userId, int $days): void
    {
        db_connect()->table('auth_remember_tokens')->where('user_id', $userId)
            ->set(['created_at' => Time::now()->subDays($days)->format('Y-m-d H:i:s')])->update();
    }

    public function testSessionWithinTheLifetimeStaysOpen(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $this->ageSession((int) $user->id, 60);

        $this->send('GET', 'backend/profile')->assertOK();
    }

    public function testAbsoluteLifetimeEndsTheSessionWithoutRememberMe(): void
    {
        config('Auth')->absoluteLifetime = 720;
        $user = $this->createUser();
        $this->login('ana@example.com');
        $this->ageSession((int) $user->id, 721);

        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('login'));

        $this->assertNull($this->loggedInUserId());
        $this->assertSame(SessionTracker::END_EXPIRED, model(UserSessionModel::class)->where('user_id', $user->id)->first()['end_reason']);
    }

    public function testRememberMeRecreatesAnExpiredSessionWithinItsOwnLifetime(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);
        $this->assertArrayHasKey('remember', $this->cookies);
        $this->ageSession((int) $user->id, 13 * 60);
        $this->ageRememberToken((int) $user->id, 2);

        $this->send('GET', 'backend/profile')->assertOK();

        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $rows = model(UserSessionModel::class)->where('user_id', $user->id)->orderBy('id')->findAll();
        $this->assertCount(2, $rows);
        $this->assertSame(SessionTracker::END_EXPIRED, $rows[0]['end_reason']);
        $this->assertNull($rows[1]['ended_at']);
        $this->assertSame(1, (int) $rows[1]['remembered']);
    }

    public function testRememberMeCannotOutliveItsOwnLifetime(): void
    {
        config('Auth')->rememberLifetime = 14;
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);
        $this->ageSession((int) $user->id, 13 * 60);
        $this->ageRememberToken((int) $user->id, 15);

        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('login'));

        $this->assertNull($this->loggedInUserId());
        $this->assertSame(0, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults(), 'The stale token is deleted.');
    }

    public function testRememberMeAloneOpensANewBrowserSessionUpToTheCap(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);

        // A new browser session (no PHP session) with only the cookie.
        $this->send('GET', 'backend/profile', [], [])->assertOK();
        $this->assertSame((int) $user->id, $this->loggedInUserId());

        $this->ageRememberToken((int) $user->id, 15);
        $this->send('GET', 'backend/profile', [], [])->assertRedirectTo(site_url('login'));
    }

    public function testRemoteLogoutEndsTheSessionAndItsRememberMe(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);
        $laptop        = $_SESSION;
        $laptopCookies = $this->cookies;
        $row           = (new SessionTracker())->current();
        $this->assertNotNull($row);

        // From another device, the user logs the laptop out.
        $this->cookies = [];
        $this->login('ana@example.com');
        $this->send('POST', 'backend/profile/sessions/' . $row['id'] . '/revoke')->assertRedirectTo(site_url('backend/profile/sessions'));

        // The laptop's next request is logged out and remember-me does not bring it back.
        $this->cookies = $laptopCookies;
        $this->send('GET', 'backend/profile', [], $laptop)->assertRedirectTo(site_url('login'));
        $this->assertNull($this->loggedInUserId());

        $this->send('GET', 'backend/profile', [], [])->assertRedirectTo(site_url('login'));
        $this->assertSame(SessionTracker::END_REVOKED, model(UserSessionModel::class)->where('id', $row['id'])->first()['end_reason']);
    }

    public function testPrivilegeChangeRegeneratesTheSessionId(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $session = $_SESSION;
        unset($session['__ci_last_regenerate']);

        // Changed by someone else (another session): flagged for the user's next request.
        $_SESSION = [];
        (new SessionTracker())->privilegesChanged((int) $user->id);
        $this->assertSame(1, (int) model(UserSessionModel::class)->where('user_id', $user->id)->first()['regenerate']);

        $this->send('GET', 'backend/profile', [], $session)->assertOK();

        $this->assertArrayHasKey('__ci_last_regenerate', $_SESSION, 'The session id was regenerated.');
        $this->assertSame(0, (int) model(UserSessionModel::class)->where('user_id', $user->id)->first()['regenerate']);
    }

    public function testIdleLockAsksForThePasswordAgain(): void
    {
        config('Auth')->idleTimeout = 10;
        $user = $this->createUser();
        $this->login('ana@example.com');

        $session                           = $_SESSION;
        $session[AuthSession::ACTIVITY_KEY] = time() - 11 * 60;

        $this->send('GET', 'backend/profile/sessions', [], $session)->assertRedirectTo(site_url('auth/lock'));
        $this->assertTrue($_SESSION[AuthSession::LOCK_KEY]);
        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('auth/lock'));
        $this->send('GET', 'auth/lock')->assertOK();

        $this->send('POST', 'auth/lock', ['password' => 'Wrong-Password-1'])->assertSee(lang('Auth.lock.wrongPassword'));
        $this->send('POST', 'auth/lock', ['password' => self::$password])->assertRedirectTo(site_url('backend/profile'));

        $this->assertArrayNotHasKey(AuthSession::LOCK_KEY, $_SESSION);
        $this->send('GET', 'backend/profile')->assertOK();
        $this->assertSame((int) $user->id, $this->loggedInUserId());
    }

    public function testIdleLockIsOffByDefault(): void
    {
        $this->createUser();
        $this->login('ana@example.com');
        $session                           = $_SESSION;
        $session[AuthSession::ACTIVITY_KEY] = time() - 5 * HOUR;

        $this->send('GET', 'backend/profile', [], $session)->assertOK();
    }
}
