<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use Modules\Auth\Filters\RequireReauth;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\TwoFactor;

/**
 * Re-authentication ("sudo mode"): password and 2FA code, 15-minute window, expiry, sessions
 * re-created by remember-me, JSON answers, the return path and the throttling.
 *
 * @internal
 */
final class ReauthTest extends TwoFactorTestCase
{
    private const PROTECTED = 'backend/profile/two-factor/app';

    public function testPasswordLoginOpensTheWindowForFifteenMinutes(): void
    {
        $this->createUser();
        $this->login('ana@example.com');

        $this->send('GET', self::PROTECTED)->assertOK();

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 14 * 60));
        $this->send('GET', self::PROTECTED)->assertOK();

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 15 * 60 + 1));
        $this->send('GET', self::PROTECTED)->assertRedirectTo(site_url('auth/reauth'));
    }

    public function testWindowFollowsTheSetting(): void
    {
        setting('Auth.reauthMinutes', 5);
        $this->createUser();
        $this->login('ana@example.com');

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 6 * 60));
        $this->send('GET', self::PROTECTED)->assertRedirectTo(site_url('auth/reauth'));
    }

    public function testConfirmingWithThePasswordReturnsToThePage(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 20 * 60));

        $this->send('GET', self::PROTECTED)->assertRedirectTo(site_url('auth/reauth'));
        $page = $this->send('GET', 'auth/reauth');
        $page->assertOK();
        $this->assertStringContainsString('layout-auth', $page->getBody());
        $this->assertStringNotContainsString('name="code"', $page->getBody(), 'No code field without 2FA.');

        $this->send('POST', 'auth/reauth', ['password' => 'Wrong-Password-99'])->assertSee(lang('Auth.reauth.wrong'));
        $this->assertFalse((new Reauth())->confirmed($user));

        $this->send('POST', 'auth/reauth', ['password' => self::$password])->assertRedirectTo(site_url(self::PROTECTED));
        $this->assertTrue((new Reauth())->confirmed($user));
        $this->send('GET', self::PROTECTED)->assertOK();
    }

    public function testUserWith2faMustAlsoTypeACode(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);
        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['code' => $this->totpCode($secret)]);

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 20 * 60));
        $this->assertFalse((new Reauth())->confirmed($user));
        $this->send('GET', 'auth/reauth')->assertSee('name="code"');

        $this->send('POST', 'auth/reauth', ['password' => self::$password])->assertSee(lang('Auth.reauth.wrong'));
        $this->send('POST', 'auth/reauth', ['password' => 'Wrong-Password-99', 'code' => $this->totpCode($secret)])->assertSee(lang('Auth.reauth.wrong'));
        $this->assertFalse((new Reauth())->confirmed($user));

        $this->send('POST', 'auth/reauth', ['password' => self::$password, 'code' => $this->totpCode($secret)]);
        $this->assertTrue((new Reauth())->confirmed($user));
    }

    public function testEmailMethodConfirmsWithAnEmailedCode(): void
    {
        $user = $this->createUser();
        (new TwoFactor())->enableEmail($user);
        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['code' => $this->emailedCode()]);
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 20 * 60));

        $this->send('POST', 'auth/reauth/send-code')->assertRedirectTo(site_url('auth/reauth'));
        $code = $this->emailedCode();

        $this->send('POST', 'auth/reauth', ['password' => self::$password, 'code' => $code]);
        $this->assertTrue((new Reauth())->confirmed($user));

        // Single use.
        (new Reauth())->forget();
        $this->send('POST', 'auth/reauth', ['password' => self::$password, 'code' => $code])->assertSee(lang('Auth.reauth.wrong'));
    }

    public function testRecoveryCodeConfirmsToo(): void
    {
        $user      = $this->createUser();
        [, $codes] = $this->enrolTotp($user);
        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['recovery_code' => $codes[0]]);
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 20 * 60));

        $this->send('POST', 'auth/reauth', ['password' => self::$password, 'recovery_code' => $codes[1]]);
        $this->assertTrue((new Reauth())->confirmed($user));
    }

    public function testSessionRecreatedByRememberMeStillRequiresIt(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);
        $this->assertTrue((new Reauth())->confirmed($user));

        // A new browser session with only the remember-me cookie.
        $this->send('GET', 'backend/profile', [], [])->assertOK();
        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $this->assertFalse((new Reauth())->confirmed($user));

        $this->send('GET', self::PROTECTED)->assertRedirectTo(site_url('auth/reauth'));
    }

    public function testRememberMeAfterTheAbsoluteLifetimeStillRequiresIt(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 10 * 60));
        db_connect()->table('user_sessions')->where('user_id', $user->id)->update(['created_at' => date('Y-m-d H:i:s', time() - 13 * 3600)]);

        $this->send('GET', 'backend/profile')->assertOK();
        $this->assertFalse((new Reauth())->confirmed($user), 'The re-created session starts without the confirmation.');
        $this->send('GET', self::PROTECTED)->assertRedirectTo(site_url('auth/reauth'));
    }

    public function testPostIsNotReplayedAndJsonGets401(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 20 * 60));

        $this->send('POST', 'backend/profile/two-factor/email')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertNull((new TwoFactor())->method((int) $user->id));

        $request = service('request');
        $this->assertInstanceOf(IncomingRequest::class, $request);
        $request->setHeader('Accept', 'application/json');
        $response = (new RequireReauth())->before($request);
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('index.php/auth/reauth', (string) $response->getBody());
    }

    public function testExternalReturnPathsAreIgnored(): void
    {
        $reauth = new Reauth();

        $reauth->rememberReturn('//evil.example/x');
        $this->assertNull($reauth->takeReturn());
        $reauth->rememberReturn('https://evil.example/');
        $this->assertNull($reauth->takeReturn());
        $reauth->rememberReturn('/backend/profile?tab=1');
        $this->assertSame('/backend/profile?tab=1', $reauth->takeReturn());
    }

    public function testGuestIsSentToTheLogin(): void
    {
        $this->send('GET', 'auth/reauth')->assertRedirectTo(site_url('login'));
        $this->assertNotNull((new RequireReauth())->before(service('request')));
    }

    public function testTooManyFailuresEndTheSession(): void
    {
        config('Auth')->loginAttemptsPerAccount = 2;
        $this->createUser();
        $this->login('ana@example.com');

        $this->send('POST', 'auth/reauth', ['password' => 'Wrong-Password-1'])->assertOK();
        $this->send('POST', 'auth/reauth', ['password' => 'Wrong-Password-2'])->assertRedirectTo(site_url('login'));
        $this->assertNull($this->loggedInUserId());
    }

    public function testLogoutForgetsTheConfirmation(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $this->assertTrue((new Reauth())->confirmed($user));

        $this->send('POST', 'logout');
        $this->assertArrayNotHasKey(Reauth::SESSION_KEY, $_SESSION);
    }

    public function testRequireReauthHelperOfBackendControllers(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $controller = new class () extends \Modules\Core\Controllers\BackendController {
            /**
             * @phpstan-impure
             */
            public function check(): ?\CodeIgniter\HTTP\ResponseInterface
            {
                return $this->requireReauth();
            }
        };
        $controller->initController(service('request'), service('response'), service('logger'));

        $this->assertNull($controller->check());

        (new Reauth())->forget();
        $response = $controller->check();
        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse((new Reauth())->confirmed($user));
    }
}
