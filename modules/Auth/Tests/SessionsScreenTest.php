<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Models\UserSessionModel;
use Modules\Auth\Services\SessionTracker;

/**
 * "My sessions": list, remote logout of one's own sessions only (forged ids refused), log out
 * the other sessions, revoke one's own API tokens.
 *
 * @internal
 */
final class SessionsScreenTest extends AuthTestCase
{
    public function testListsTheOpenSessionsAndMarksTheCurrentOne(): void
    {
        $this->createUser();
        service('superglobals')->setServer('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0');
        $this->login('ana@example.com');
        $first = $_SESSION;
        $this->cookies = [];
        $this->login('ana@example.com');

        $response = $this->send('GET', 'backend/profile/sessions');

        $response->assertOK();
        $response->assertSee(lang('Auth.sessions.current'));
        $response->assertSee('Firefox 133');
        $this->assertSame(1, substr_count($response->getBody(), '/revoke"'), 'Only the other session has a log-out button.');
        $this->assertNotSame([], $first);
    }

    public function testCannotLogOutSomeoneElsesSession(): void
    {
        $other = $this->createUser('joan@example.com');
        $this->login('joan@example.com');
        $row = (new SessionTracker())->current();
        $this->assertNotNull($row);

        $this->cookies = [];
        $this->createUser('ana@example.com');
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile/sessions/' . $row['id'] . '/revoke')->assertRedirectTo(site_url('backend/profile/sessions'));

        $this->assertSame(lang('Auth.sessions.notFound'), $_SESSION['error'] ?? null);
        $this->assertNull(model(UserSessionModel::class)->where('id', $row['id'])->first()['ended_at'], "Joan's session is untouched.");
        $this->assertNotNull($other->id);
    }

    public function testCannotRevokeTheCurrentSessionFromTheList(): void
    {
        $this->createUser();
        $this->login('ana@example.com');
        $row = (new SessionTracker())->current();

        $this->send('POST', 'backend/profile/sessions/' . $row['id'] . '/revoke');

        $this->assertSame(lang('Auth.sessions.useLogout'), $_SESSION['error'] ?? null);
        $this->assertNull(model(UserSessionModel::class)->where('id', $row['id'])->first()['ended_at']);
    }

    public function testLogOutOtherSessions(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $this->cookies = [];
        $this->login('ana@example.com');
        $this->cookies = [];
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile/sessions/revoke-others')->assertRedirectTo(site_url('backend/profile/sessions'));

        $this->assertSame(1, model(UserSessionModel::class)->where('user_id', $user->id)->where('ended_at', null)->countAllResults());
        $this->send('GET', 'backend/profile')->assertOK();
    }

    public function testRevokeOwnTokenButNotAnotherUsersToken(): void
    {
        $joan  = $this->createUser('joan@example.com');
        $their = $joan->generateAccessToken('Joan app');
        $user  = $this->createUser('ana@example.com');
        $mine  = $user->generateAccessToken('Ana app');
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile/sessions')->assertSee('Ana app');

        $this->send('POST', 'backend/profile/tokens/' . $their->id . '/revoke');
        $this->assertSame(lang('Auth.sessions.tokenNotFound'), $_SESSION['error'] ?? null);
        $this->assertCount(1, model(UserModel::class)->findById($joan->id)?->accessTokens() ?? []);

        $this->send('POST', 'backend/profile/tokens/' . $mine->id . '/revoke');
        $this->assertSame(lang('Auth.sessions.tokenRevoked'), $_SESSION['success'] ?? null);
        $this->assertCount(0, model(UserModel::class)->findById($user->id)?->accessTokens() ?? []);
    }

    public function testForgedPostWithoutTheCsrfTokenIsRefused(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $this->cookies = [];
        $this->login('ana@example.com');

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);

        try {
            $this->send('POST', 'backend/profile/sessions/revoke-others', ['csrf_test_name' => 'forged']);
        } finally {
            $this->assertSame(2, model(UserSessionModel::class)->where('user_id', $user->id)->where('ended_at', null)->countAllResults());
        }
    }

    public function testRoutesDeclareTheirPermissions(): void
    {
        $expected = [
            'backend/profile'                         => 'can:auth-profile.view',
            'backend/profile/password'                => 'can:auth-profile.view',
            'backend/profile/sessions'                => 'can:auth-sessions.view',
        ];
        $routes = service('routes')->loadRoutes();

        foreach ($expected as $path => $filter) {
            $this->assertContains($filter, $routes->getFiltersForRoute($path, 'GET'), $path);
        }

        foreach (['backend/profile' => 'can:auth-profile.edit', 'backend/profile/sessions/revoke-others' => 'can:auth-sessions.revoke', 'backend/profile/sessions/([0-9]+)/revoke' => 'can:auth-sessions.revoke', 'backend/profile/tokens/([0-9]+)/revoke' => 'can:auth-sessions.revoke'] as $path => $filter) {
            $this->assertContains($filter, $routes->getFiltersForRoute($path, 'POST'), $path);
        }
    }
}
