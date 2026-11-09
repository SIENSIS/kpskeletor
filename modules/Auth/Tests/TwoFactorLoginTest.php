<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Authentication\UserTwoFactorAction;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\TwoFactor;

/**
 * Login with 2FA: email codes, authenticator app (drift ±1 step, no reuse), recovery codes,
 * throttling and the admin reset (acceptance criterion 5).
 *
 * @internal
 */
final class TwoFactorLoginTest extends TwoFactorTestCase
{
    public function testUserWithout2faLogsInDirectly(): void
    {
        $user = $this->createUser();

        $this->login('ana@example.com')->assertRedirectTo(site_url('backend'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $this->assertFalse($this->isPending());
    }

    public function testEmailCodeLogin(): void
    {
        $user = $this->createUser();
        (new TwoFactor())->enableEmail($user);
        $this->sentEmails = [];

        $this->login('ana@example.com')->assertRedirectTo(site_url('auth/a/show'));
        $this->assertTrue($this->isPending(), 'The login waits for the code.');
        $code = $this->emailedCode();

        $identity = model(UserIdentityModel::class)->getIdentityByType($user, UserTwoFactorAction::TYPE);
        $this->assertNotNull($identity);
        $this->assertNotSame($code, $identity->secret2, 'Only the hash of the code is stored.');

        // The back office stays closed: Shield's `session` filter sends a pending login to the code.
        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('auth/a/show'));

        $page = $this->send('GET', 'auth/a/show');
        $page->assertOK();
        $this->assertStringContainsString('layout-auth', $page->getBody());
        $this->assertStringContainsString('a**@example.com', $page->getBody());

        $wrong = $this->send('POST', 'auth/a/verify', ['code' => $code === '000000' ? '111111' : '000000']);
        $wrong->assertOK();
        $wrong->assertSee(lang('Auth.twoFactor.wrongCode'));
        $this->assertTrue($this->isPending());

        $this->send('POST', 'auth/a/verify', ['code' => $code])->assertRedirectTo(site_url('backend'));
        $this->assertFalse($this->isPending());
        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $this->send('GET', 'backend/profile')->assertOK();
        $this->assertTrue((new Reauth())->confirmed($user), 'A password login with its code counts as re-authentication.');
    }

    public function testEmailCodeExpires(): void
    {
        $user = $this->createUser();
        (new TwoFactor())->enableEmail($user);
        $this->login('ana@example.com');
        $code = $this->emailedCode();

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 11 * 60));

        $this->send('POST', 'auth/a/verify', ['code' => $code])->assertRedirectTo(site_url('login'));
        $this->assertNull($this->loggedInUserId());
    }

    public function testNewEmailCodeReplacesTheOldOne(): void
    {
        $user = $this->createUser();
        (new TwoFactor())->enableEmail($user);
        $this->login('ana@example.com');
        $first = $this->emailedCode();

        $this->send('POST', 'auth/a/handle')->assertRedirectTo(site_url('auth/a/show'));
        $second = $this->emailedCode();

        if ($first !== $second) {
            $this->send('POST', 'auth/a/verify', ['code' => $first])->assertSee(lang('Auth.twoFactor.wrongCode'));
        }

        $this->send('POST', 'auth/a/verify', ['code' => $second])->assertRedirectTo(site_url('backend'));
    }

    public function testAuthenticatorAppLogin(): void
    {
        $user        = $this->createUser();
        [$secret]    = $this->enrolTotp($user);
        $this->sentEmails = [];

        $this->login('ana@example.com')->assertRedirectTo(site_url('auth/a/show'));
        $this->assertSame([], $this->sentEmails, 'The app method sends no email.');
        $this->send('GET', 'auth/a/show')->assertSee(lang('Auth.twoFactor.enterAppCode'));

        $this->send('POST', 'auth/a/verify', ['code' => $this->totpCode($secret)])->assertRedirectTo(site_url('backend'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());
    }

    public function testAppCodeIsNeverAcceptedTwice(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);
        $code     = $this->totpCode($secret);

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['code' => $code])->assertRedirectTo(site_url('backend'));
        $this->send('POST', 'logout');

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['code' => $code])->assertSee(lang('Auth.twoFactor.wrongCode'));
        $this->assertTrue($this->isPending());

        // An earlier step than the last accepted one is refused too.
        $this->send('POST', 'auth/a/verify', ['code' => $this->totpCode($secret, -1)])->assertSee(lang('Auth.twoFactor.wrongCode'));
    }

    public function testAppCodeDriftOfOneStepIsAccepted(): void
    {
        $user      = $this->createUser();
        $twoFactor = new TwoFactor();
        $secret    = $twoFactor->totp()->newSecret();
        $this->assertNotNull($twoFactor->enableTotp($user, $secret, $this->totpCode($secret, -1)));

        // One step ahead (the phone's clock is 30 s fast).
        $this->assertTrue($twoFactor->verifyTotp((int) $user->id, $this->totpCode($secret, 1)));

        // Two steps away is out of the window.
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 5 * 30));
        $this->assertFalse($twoFactor->verifyTotp((int) $user->id, $this->totpCode($secret, -2)));
        $this->assertFalse($twoFactor->verifyTotp((int) $user->id, $this->totpCode($secret, 2)));
        // One step behind (and later than the last used step) passes.
        $this->assertTrue($twoFactor->verifyTotp((int) $user->id, $this->totpCode($secret, -1)));
    }

    public function testTotpSecretIsStoredEncrypted(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);

        $row = db_connect()->table('user_two_factor')->where('user_id', $user->id)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('totp', $row['method']);
        $this->assertSame(32, strlen($secret), '160-bit Base32 secret.');
        $this->assertStringNotContainsString($secret, (string) $row['totp_secret']);
        $this->assertSame($secret, service('encrypter')->decrypt((string) base64_decode((string) $row['totp_secret'], true)));
    }

    public function testRecoveryCodeLogsInOnce(): void
    {
        $user            = $this->createUser();
        [, $codes]       = $this->enrolTotp($user);
        $this->assertCount(10, $codes);
        $this->assertSame(0, db_connect()->table('user_recovery_codes')->whereIn('code_hash', $codes)->countAllResults(), 'Only hashes are stored.');

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['recovery_code' => strtolower($codes[0])])->assertRedirectTo(site_url('backend'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $this->assertSame(9, (new TwoFactor())->recoveryCodesLeft((int) $user->id));
        $this->send('POST', 'logout');

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['recovery_code' => $codes[0]])->assertSee(lang('Auth.twoFactor.wrongCode'));
        $this->assertTrue($this->isPending());
    }

    public function testRecoveryCodeWorksWithEmailMethod(): void
    {
        $user  = $this->createUser();
        $codes = (new TwoFactor())->enableEmail($user);

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['recovery_code' => $codes[3]])->assertRedirectTo(site_url('backend'));
    }

    public function testTooManyWrongCodesEndTheLogin(): void
    {
        config('Auth')->loginAttemptsPerAccount = 3;
        $user = $this->createUser();
        $this->enrolTotp($user);

        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['code' => '000001'])->assertOK();
        $this->send('POST', 'auth/a/verify', ['code' => '000002'])->assertOK();
        $this->send('POST', 'auth/a/verify', ['code' => '000003'])->assertRedirectTo(site_url('login'));

        $this->assertNull($this->loggedInUserId());
        $this->assertFalse($this->isPending());
    }

    public function testCancelEndsThePendingLogin(): void
    {
        $user = $this->createUser();
        $this->enrolTotp($user);
        $this->login('ana@example.com');

        $this->send('POST', 'auth/a/cancel')->assertRedirectTo(site_url('login'));
        $this->assertFalse($this->isPending());
        $this->assertNull(model(UserIdentityModel::class)->getIdentityByType($user, UserTwoFactorAction::TYPE));
    }

    public function testActionRoutesAnswer404WithoutAPendingLogin(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);

        $this->send('GET', 'auth/a/show');
    }

    public function testMagicLinkLoginAlsoAsksForTheCode(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);

        $this->send('POST', 'login/magic-link', ['email' => 'ana@example.com']);
        $token = $this->linkToken('login/verify-magic-link');

        $this->send('GET', 'login/verify-magic-link?token=' . $token)->assertRedirectTo(site_url('auth/a/show'));
        $this->assertTrue($this->isPending());

        $this->send('POST', 'auth/a/verify', ['code' => $this->totpCode($secret)])->assertRedirectTo(site_url('backend'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());
        $this->assertFalse((new Reauth())->confirmed($user), 'A magic link is not a re-authentication.');
    }

    public function testAdminResetTurns2faOff(): void
    {
        $user = $this->createUser();
        $this->enrolTotp($user);
        $fired = null;
        \CodeIgniter\Events\Events::on('twoFactorReset', static function (\CodeIgniter\Shield\Entities\User $who, ?int $by = null) use (&$fired): void {
            $fired = [(int) $who->id, $by];
        });

        (new TwoFactor())->reset($user, 99);

        $this->assertNull((new TwoFactor())->method((int) $user->id));
        $this->assertSame(0, (new TwoFactor())->recoveryCodesLeft((int) $user->id));
        $this->assertSame([(int) $user->id, 99], $fired);

        $this->login('ana@example.com')->assertRedirectTo(site_url('backend'));
    }

    public function testRememberMeIsIssuedOnlyAfterTheCode(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);

        $this->login('ana@example.com', null, true)->assertRedirectTo(site_url('auth/a/show'));
        $this->assertSame(0, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults(), 'No token with the password alone.');
        $this->assertSame([], array_filter(array_keys($this->cookies), static fn (string $name): bool => str_contains($name, 'remember')));

        // Even if the pending step disappears (admin reset), a new browser session is a guest.
        (new TwoFactor())->reset($user);
        $this->send('GET', 'backend/profile', [], [])->assertRedirectTo(site_url('login'));
        $this->assertNull($this->loggedInUserId());
    }

    public function testRememberMeAfterTheCodeOpensANewBrowserSession(): void
    {
        $user     = $this->createUser();
        [$secret] = $this->enrolTotp($user);

        $this->login('ana@example.com', null, true);
        $this->send('POST', 'auth/a/verify', ['code' => $this->totpCode($secret)])->assertRedirectTo(site_url('backend'));
        $this->assertSame(1, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults());

        $this->send('GET', 'backend/profile', [], [])->assertOK();
        $this->assertSame((int) $user->id, $this->loggedInUserId());
    }
}
