<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use Modules\Auth\Services\TwoFactor;

/**
 * The app login (`api/v1/auth/token` → `auth/token/verify`): the user's active second factor
 * (authenticator app, email code, recovery code), an emailed code for users without 2FA,
 * wrong and reused codes, throttling, refresh and logout.
 *
 * @internal
 */
final class AppLoginTest extends ApiTestCase
{
    public function testUserWithoutTwoFactorGetsATokenOnlyWithTheEmailedCode(): void
    {
        $this->createUser('ana@example.com');

        $challenge = $this->startLogin('ana@example.com');
        $this->assertSame('email', $challenge['method']);
        $this->assertCount(1, $this->sentEmails);

        $email = (string) $this->lastEmail()['body'];
        $this->assertStringContainsString('Test phone', $email, 'The email names the device.');
        $this->assertStringContainsString('192.0.2.10', $email, 'The email names the IP address.');
        $this->assertStringContainsString('auth/api-login/' . $challenge['challenge'], $email, 'The email links to the deny page.');

        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => '000000'])->assertStatus(422);

        $response = $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $this->emailedCode()]);
        $response->assertStatus(201);
        $data = $this->json($response)['data'];
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertNotNull($data['expires_at'], 'App-login tokens expire.');

        $this->api('GET', 'api/v1/me', null, $data['token'])->assertStatus(200);
    }

    public function testTheEmailedCodeWorksOnlyOnce(): void
    {
        $this->createUser('ana@example.com');
        $challenge = $this->startLogin('ana@example.com');
        $code      = $this->emailedCode();

        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $code])->assertStatus(201);
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $code])->assertStatus(422);
    }

    public function testTooManyWrongCodesCloseTheChallenge(): void
    {
        $this->createUser('ana@example.com');
        $challenge = $this->startLogin('ana@example.com');
        $code      = $this->emailedCode();

        for ($i = 0; $i < config('Kpapy')->challengeAttempts; $i++) {
            $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => '111111'])->assertStatus(422);
        }

        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $code])->assertStatus(422);
    }

    public function testUserWithEmailTwoFactorUsesTheEmailedCodeOrARecoveryCode(): void
    {
        $user  = $this->createUser('ana@example.com');
        $codes = (new TwoFactor())->enableEmail($user);

        $challenge = $this->startLogin('ana@example.com');
        $this->assertSame('email', $challenge['method']);
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $this->emailedCode()])->assertStatus(201);

        $challenge = $this->startLogin('ana@example.com');
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $codes[0]])->assertStatus(201);
        $this->assertSame(9, (new TwoFactor())->recoveryCodesLeft((int) $user->id));
    }

    public function testUserWithTheAuthenticatorAppUsesItsCodeAndNoEmailIsSent(): void
    {
        $user        = $this->createUser('ana@example.com');
        [$secret]    = $this->enrolTotp($user);
        $this->sentEmails = [];

        $challenge = $this->startLogin('ana@example.com');
        $this->assertSame('totp', $challenge['method']);
        $this->assertSame([], $this->sentEmails);

        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => '123456'])->assertStatus(422);
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $this->totpCode($secret)])->assertStatus(201);
    }

    public function testAnAuthenticatorCodeIsNeverAcceptedTwice(): void
    {
        $user     = $this->createUser('ana@example.com');
        [$secret] = $this->enrolTotp($user);
        $code     = $this->totpCode($secret);

        $first = $this->startLogin('ana@example.com');
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $first['challenge'], 'code' => $code])->assertStatus(201);

        $second = $this->startLogin('ana@example.com');
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $second['challenge'], 'code' => $code])->assertStatus(422);
    }

    public function testAuthenticatorUsersCanUseARecoveryCodeOnce(): void
    {
        $user              = $this->createUser('ana@example.com');
        [, $recoveryCodes] = $this->enrolTotp($user);

        $first = $this->startLogin('ana@example.com');
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $first['challenge'], 'code' => $recoveryCodes[3]])->assertStatus(201);

        $second = $this->startLogin('ana@example.com');
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $second['challenge'], 'code' => $recoveryCodes[3]])->assertStatus(422);
    }

    public function testWrongPasswordsAreGenericAndThrottledWithTheWebLogin(): void
    {
        $this->createUser('ana@example.com');

        $wrong = $this->api('POST', 'api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'wrong-password']);
        $wrong->assertStatus(401);
        $unknown = $this->api('POST', 'api/v1/auth/token', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);
        $unknown->assertStatus(401);
        $this->assertSame($this->json($wrong)['code'], $this->json($unknown)['code'], 'The same answer whether the account exists or not.');
        $this->assertSame('application/problem+json', explode(';', $wrong->response()->getHeaderLine('Content-Type'))[0]);

        // Auth's LoginThrottle (per account) also blocks the right password until the window ends.
        $attempts = (new \Modules\Auth\Services\AuthPolicy())->loginAttemptsPerAccount();

        for ($i = 1; $i < $attempts; $i++) {
            $this->api('POST', 'api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'wrong-' . $i]);
        }

        $blocked = $this->api('POST', 'api/v1/auth/token', ['email' => 'ana@example.com', 'password' => self::$password]);
        $blocked->assertStatus(429);
        $this->assertNotSame('', $blocked->response()->getHeaderLine('Retry-After'));
    }

    public function testAUserWhoMustChangeThePasswordGetsNoChallenge(): void
    {
        $user = $this->createUser('ana@example.com');
        $user->forcePasswordReset();

        $response = $this->api('POST', 'api/v1/auth/token', ['email' => 'ana@example.com', 'password' => self::$password]);
        $response->assertStatus(403);
        $this->assertSame('password-reset-required', $this->json($response)['code']);
        $this->assertSame([], $this->sentEmails);
    }

    public function testRefreshReplacesAnAppTokenAndLogoutRevokesIt(): void
    {
        $this->createUser('ana@example.com');
        $challenge = $this->startLogin('ana@example.com');
        $token     = $this->json($this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $this->emailedCode()]))['data']['token'];

        $refreshed = $this->api('POST', 'api/v1/auth/token/refresh', [], $token);
        $refreshed->assertStatus(201);
        $new = $this->json($refreshed)['data']['token'];

        $this->api('GET', 'api/v1/me', null, $token)->assertStatus(401);
        $this->api('GET', 'api/v1/me', null, $new)->assertStatus(200);

        $this->api('DELETE', 'api/v1/auth/token', null, $new)->assertStatus(204);
        $this->api('GET', 'api/v1/me', null, $new)->assertStatus(401);
    }

    public function testPersonalTokensCannotBeRefreshed(): void
    {
        $user  = $this->createUser('ana@example.com', 'admin');
        $token = (new \Modules\Api\Services\PersonalTokens())->create($user, 'Integration', ['*'], 30)['token'];

        $response = $this->api('POST', 'api/v1/auth/token/refresh', [], $token);
        $response->assertStatus(403);
        $this->assertSame('personal-token', $this->json($response)['code']);
    }

    public function testTheDenyLinkStopsTheSignIn(): void
    {
        $this->createUser('ana@example.com');
        $challenge = $this->startLogin('ana@example.com');
        $code      = $this->emailedCode();

        $page = $this->send('GET', 'auth/api-login/' . $challenge['challenge']);
        $page->assertOK();
        $page->assertSee('Test phone');

        $this->send('POST', 'auth/api-login/' . $challenge['challenge'])->assertOK();
        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $code])->assertStatus(422);

        $this->send('GET', 'auth/api-login/' . $challenge['challenge'])->assertDontSee('Test phone');
    }

    public function testTheDenyFormNeedsTheCsrfToken(): void
    {
        $this->createUser('ana@example.com');
        $challenge = $this->startLogin('ana@example.com');
        $code      = $this->emailedCode();

        try {
            $this->send('POST', 'auth/api-login/' . $challenge['challenge'], ['csrf_test_name' => 'forged']);
            $this->fail('A forged POST must be refused.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            // CSRF refused it.
        }

        $this->api('POST', 'api/v1/auth/token/verify', ['challenge' => $challenge['challenge'], 'code' => $code])->assertStatus(201);
    }
}
