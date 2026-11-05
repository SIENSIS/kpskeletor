<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Auth\Models\UserSessionModel;

/**
 * Password login and logout: generic messages, per-IP and per-account throttling (429), CSRF,
 * banned accounts, session tracking.
 *
 * @internal
 */
final class LoginTest extends AuthTestCase
{
    public function testLoginPageUsesTheAuthLayout(): void
    {
        $response = $this->send('GET', 'login');

        $response->assertOK();
        $this->assertStringContainsString('layout-auth', $response->getBody());
        $this->assertStringContainsString('name="email"', $response->getBody());
        $this->assertStringNotContainsString('name="captcha"', $response->getBody());
    }

    public function testSuccessfulLoginRedirectsAndTracksTheSession(): void
    {
        $user = $this->createUser();

        $response = $this->login('ana@example.com');

        $response->assertRedirectTo(site_url('backend/profile'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());

        $rows = model(UserSessionModel::class)->where('user_id', $user->id)->findAll();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['ended_at']);
        $this->assertSame(64, strlen((string) $rows[0]['token_hash']));
        $this->assertNotSame($_SESSION['kp_auth_session'], $rows[0]['token_hash'], 'Only the hash of the tracking token is stored.');
    }

    public function testWrongPasswordAndUnknownEmailGetTheSameGenericMessage(): void
    {
        $this->createUser();

        $wrong   = $this->login('ana@example.com', 'Not-The-Password-1');
        $unknown = $this->login('nobody@example.com', 'Not-The-Password-1');

        $wrong->assertOK();
        $unknown->assertOK();
        $wrong->assertSee(lang('Auth.msg.badLogin'));
        $unknown->assertSee(lang('Auth.msg.badLogin'));
        $this->assertNull($this->loggedInUserId());
        $this->assertStringNotContainsString('nobody@example.com', strip_tags(str_replace('value="nobody@example.com"', '', $unknown->getBody())));
    }

    public function testPostWithoutCsrfTokenIsRefused(): void
    {
        $this->createUser();

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);

        $this->send('POST', 'login', ['email' => 'ana@example.com', 'password' => self::$password, 'csrf_test_name' => 'forged'], ['csrf_test_name' => 'real-token']);
    }

    public function testFailuresPerAccountAnswer429(): void
    {
        config('Auth')->loginAttemptsPerAccount = 3;
        config('Auth')->loginAttemptsPerIp      = 100;
        $this->createUser();

        foreach (['192.0.2.1', '192.0.2.2', '192.0.2.3'] as $ip) {
            service('superglobals')->setServer('REMOTE_ADDR', $ip);
            $this->login('ana@example.com', 'Wrong-Password-1')->assertOK();
        }

        service('superglobals')->setServer('REMOTE_ADDR', '192.0.2.4');
        $response = $this->login('ana@example.com');

        $response->assertStatus(429);
        $this->assertNotSame('', $response->response()->getHeaderLine('Retry-After'));
        $this->assertNull($this->loggedInUserId(), 'Even the right password is refused while the account is locked.');
    }

    public function testFailuresPerIpAnswer429(): void
    {
        config('Auth')->loginAttemptsPerIp      = 3;
        config('Auth')->loginAttemptsPerAccount = 100;
        $this->createUser('ana@example.com');
        $this->createUser('joan@example.com');

        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $this->login($email, 'Wrong-Password-1')->assertOK();
        }

        $this->login('joan@example.com')->assertStatus(429);

        service('superglobals')->setServer('REMOTE_ADDR', '198.51.100.7');
        $this->login('joan@example.com')->assertRedirect();
    }

    public function testBannedUserLearnsItOnlyWithTheRightPassword(): void
    {
        $user = $this->createUser();
        $user->ban('Spam');

        $this->login('ana@example.com', 'Wrong-Password-1')->assertSee(lang('Auth.msg.badLogin'));
        $this->login('ana@example.com')->assertSee('Spam');
        $this->assertNull($this->loggedInUserId());
    }

    public function testLogoutNeedsAPostAndEndsTheTrackedSession(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $this->assertStringContainsString('action="' . site_url('logout') . '"', $this->send('GET', 'logout')->getBody());
        $this->assertSame((int) $user->id, $this->loggedInUserId(), 'GET logout only asks for confirmation.');

        $this->send('POST', 'logout')->assertRedirectTo(site_url('login'));

        $this->assertNull($this->loggedInUserId());
        $row = model(UserSessionModel::class)->where('user_id', $user->id)->first();
        $this->assertSame('logout', $row['end_reason']);
    }

    public function testBackOfficeRedirectsGuestsToTheLogin(): void
    {
        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('login'));
    }
}
