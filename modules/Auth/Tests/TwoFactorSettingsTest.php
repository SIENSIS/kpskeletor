<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\I18n\Time;
use Modules\Auth\Controllers\TwoFactorSettingsController;
use Modules\Auth\Services\Reauth;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Groups\GroupRegistry;

/**
 * The profile's 2FA screens: enabling email and app (QR as local SVG, a valid code before
 * saving), recovery codes shown once, the group policy that forces set-up, and the permission
 * and re-authentication rules (also for forged POSTs).
 *
 * @internal
 */
final class TwoFactorSettingsTest extends TwoFactorTestCase
{
    public function testUserEnablesEmail2fa(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile/two-factor')->assertOK();
        $this->send('POST', 'backend/profile/two-factor/email')->assertRedirectTo(site_url('backend/profile/two-factor'));

        $this->assertSame(TwoFactor::EMAIL, (new TwoFactor())->method((int) $user->id));
        $page = $this->send('GET', 'backend/profile/two-factor');
        $page->assertSee(lang('Auth.twoFactor.codesIntro'));
        $this->assertSame(10, preg_match_all('/[2-9A-HJKMNP-Z]{5}-[2-9A-HJKMNP-Z]{5}/', $page->getBody()));

        // Shown once.
        $this->send('GET', 'backend/profile/two-factor')->assertDontSee(lang('Auth.twoFactor.codesIntro'));
    }

    public function testUserEnablesTheAuthenticatorApp(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $page = $this->send('GET', 'backend/profile/two-factor/app');
        $page->assertOK();
        $this->assertStringContainsString('src="data:image/svg+xml;base64,', $page->getBody(), 'The QR code is a local SVG.');
        $secret = $_SESSION[TwoFactorSettingsController::PENDING_SECRET];
        $this->assertStringContainsString(substr($secret, 0, 4), $page->getBody());
        $this->assertNull((new TwoFactor())->method((int) $user->id), 'Nothing is saved before a valid code.');

        $this->send('POST', 'backend/profile/two-factor/app', ['code' => '000000'])->assertRedirectTo(site_url('backend/profile/two-factor/app'));
        $this->assertNull((new TwoFactor())->method((int) $user->id));

        $this->send('POST', 'backend/profile/two-factor/app', ['code' => $this->totpCode($secret)])->assertRedirectTo(site_url('backend/profile/two-factor'));
        $this->assertSame(TwoFactor::TOTP, (new TwoFactor())->method((int) $user->id));
        $this->assertArrayNotHasKey(TwoFactorSettingsController::PENDING_SECRET, $_SESSION);
    }

    public function testAppIsUnavailableWithoutEncryptionKey(): void
    {
        config('Encryption')->key = '';
        $this->createUser();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile/two-factor/app')->assertRedirectTo(site_url('backend/profile/two-factor'));
    }

    public function testUserDisables2faAndRegeneratesCodes(): void
    {
        $user      = $this->createUser();
        [, $codes] = $this->enrolTotp($user);
        $this->login('ana@example.com');
        $this->send('POST', 'auth/a/verify', ['recovery_code' => $codes[0]]);

        $this->send('POST', 'backend/profile/two-factor/recovery-codes')->assertRedirectTo(site_url('backend/profile/two-factor'));
        $this->assertSame(10, (new TwoFactor())->recoveryCodesLeft((int) $user->id));
        $this->assertFalse((new \Modules\Auth\Services\RecoveryCodes())->consume((int) $user->id, $codes[1]), 'The old codes stop working.');

        $this->send('POST', 'backend/profile/two-factor/disable')->assertRedirectTo(site_url('backend/profile/two-factor'));
        $this->assertNull((new TwoFactor())->method((int) $user->id));
        $this->assertSame(0, (new TwoFactor())->recoveryCodesLeft((int) $user->id));
    }

    public function testGroupPolicyForcesSetUpAfterLogin(): void
    {
        (new GroupRegistry())->setTwoFactor('user', GroupRegistry::REQUIRED);
        $user = $this->createUser();

        $this->login('ana@example.com')->assertRedirectTo(site_url('backend'));
        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('backend/profile/two-factor'));
        $this->send('GET', 'backend/profile/sessions')->assertRedirectTo(site_url('backend/profile/two-factor'));
        $page = $this->send('GET', 'backend/profile/two-factor');
        $page->assertOK();
        $page->assertSee(lang('Auth.twoFactor.required'));

        $this->send('POST', 'backend/profile/two-factor/email');
        $this->send('GET', 'backend/profile')->assertOK();

        // The policy forbids turning it off.
        $this->send('POST', 'backend/profile/two-factor/disable')->assertRedirectTo(site_url('backend/profile/two-factor'));
        $this->assertSame(TwoFactor::EMAIL, (new TwoFactor())->method((int) $user->id));
    }

    public function testOptionalGroupIsNotForced(): void
    {
        (new GroupRegistry())->setTwoFactor('admin', GroupRegistry::REQUIRED);
        $this->createUser();

        $this->login('ana@example.com');
        $this->send('GET', 'backend/profile')->assertOK();
    }

    public function testChangesNeedRecentReauthentication(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        Time::setTestNow(Time::createFromTimestamp(self::NOW + 16 * 60));

        $this->send('POST', 'backend/profile/two-factor/email')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertNull((new TwoFactor())->method((int) $user->id));
        $this->send('GET', 'backend/profile/two-factor/app')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame('/backend/profile/two-factor/app', $_SESSION[Reauth::RETURN_KEY]);
    }

    public function testForgedPostWithoutCsrfIsRefused(): void
    {
        $this->createUser();
        $this->login('ana@example.com');

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);

        $this->send('POST', 'backend/profile/two-factor/disable', ['csrf_test_name' => 'forged']);
    }

    public function testWithoutEditPermissionChangesAreForbidden(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');
        $matrix         = setting('AuthGroups.matrix');
        $matrix['user'] = ['auth-profile.view'];
        setting('AuthGroups.matrix', $matrix);

        $this->send('POST', 'backend/profile/two-factor/email')->assertStatus(403);
        $this->assertNull((new TwoFactor())->method((int) $user->id));
    }
}
