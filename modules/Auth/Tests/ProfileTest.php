<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Models\UserProfileModel;
use Modules\Auth\Models\UserSessionModel;

/**
 * The profile: fields saved in `user_profiles`, email change guarded by the password, password
 * change ends the other sessions and revokes the app tokens, forced reset.
 *
 * @internal
 */
final class ProfileTest extends AuthTestCase
{
    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name'       => 'Anna Puig',
            'email'      => 'ana@example.com',
            'locale'     => 'es',
            'timezone'   => 'Europe/Madrid',
            'theme_mode' => 'dark',
            'birth_date' => '1990-05-17',
        ];
    }

    public function testProfileIsSavedAndAppliesLanguageAndColourMode(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile')->assertOK();
        $this->send('POST', 'backend/profile', $this->form())->assertRedirectTo(site_url('backend/profile'));

        $profile = model(UserProfileModel::class)->forUser((int) $user->id);
        $this->assertSame('Anna Puig', $profile['name']);
        $this->assertSame('es', $profile['locale']);
        $this->assertSame('dark', $profile['theme_mode']);
        $this->assertSame('1990-05-17', $profile['birth_date']);
        $this->assertSame('dark', $this->cookies['kp_theme'] ?? null);

        $this->send('GET', 'backend/profile')->assertSee('Mi perfil');
    }

    public function testInvalidValuesAreRefused(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile', $this->form(['locale' => 'fr', 'timezone' => 'Mars/Olympus', 'theme_mode' => 'pink']));

        $this->assertSame(['locale', 'timezone', 'theme_mode'], array_keys($_SESSION['errors'] ?? []));
        $this->assertNull(model(UserProfileModel::class)->where('user_id', $user->id)->first());
    }

    public function testEmailChangeNeedsTheCurrentPassword(): void
    {
        $user = $this->createUser();
        $this->createUser('joan@example.com');
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile', $this->form(['email' => 'anna@example.com']));
        $this->assertSame(lang('Auth.profile.emailNeedsPassword'), $_SESSION['errors']['email'] ?? null);

        $this->send('POST', 'backend/profile', $this->form(['email' => 'joan@example.com', 'current_password' => self::$password]));
        $this->assertSame(lang('Auth.profile.emailTaken'), $_SESSION['errors']['email'] ?? null);

        $this->send('POST', 'backend/profile', $this->form(['email' => 'anna@example.com', 'current_password' => self::$password]));
        $this->assertSame('anna@example.com', model(UserModel::class)->findById($user->id)?->email);
    }

    public function testPasswordChangeEndsOtherSessionsAndRevokesAppTokens(): void
    {
        $user = $this->createUser();
        $user->generateAccessToken('Phone app');
        $this->login('ana@example.com', null, true);
        $this->cookies = [];
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile/password', [
            'current_password' => self::$password,
            'password'         => 'A-brand-new-passphrase-77',
            'password_confirm' => 'A-brand-new-passphrase-77',
        ])->assertRedirectTo(site_url('backend/profile'));

        $this->assertSame(lang('Auth.profile.passwordChanged', [1]), $_SESSION['success'] ?? null);
        $this->assertSame(1, model(UserSessionModel::class)->where('user_id', $user->id)->where('ended_at', null)->countAllResults());
        $this->assertSame(0, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults());
        $this->assertSame([], model(UserModel::class)->findById($user->id)?->accessTokens());
        $this->send('GET', 'backend/profile')->assertOK();
    }

    public function testWrongCurrentPasswordIsRefusedAndThrottled(): void
    {
        config('Auth')->loginAttemptsPerAccount = 2;
        $this->createUser();
        $this->login('ana@example.com');

        foreach ([1, 2] as $attempt) {
            $this->send('POST', 'backend/profile/password', ['current_password' => 'Wrong-' . $attempt, 'password' => 'A-brand-new-passphrase-77', 'password_confirm' => 'A-brand-new-passphrase-77']);
            $this->assertSame(lang('Auth.profile.currentPasswordWrong'), $_SESSION['errors']['current_password'] ?? null);
        }

        $this->send('POST', 'backend/profile/password', ['current_password' => self::$password, 'password' => 'A-brand-new-passphrase-77', 'password_confirm' => 'A-brand-new-passphrase-77']);
        $this->assertStringContainsString('minutes', (string) ($_SESSION['error'] ?? ''));
    }

    public function testWrongPasswordForAnEmailChangeIsThrottled(): void
    {
        config('Auth')->loginAttemptsPerAccount = 2;
        $user = $this->createUser();
        $this->login('ana@example.com');

        foreach ([1, 2] as $attempt) {
            $this->send('POST', 'backend/profile', $this->form(['email' => 'anna@example.com', 'current_password' => 'Wrong-' . $attempt]));
            $this->assertSame(lang('Auth.profile.emailNeedsPassword'), $_SESSION['errors']['email'] ?? null);
        }

        // Blocked: even the right password is refused until the lock time passes.
        $this->send('POST', 'backend/profile', $this->form(['email' => 'anna@example.com', 'current_password' => self::$password]));
        $this->assertStringContainsString('minutes', (string) ($_SESSION['errors']['email'] ?? ''));
        $this->assertSame('ana@example.com', model(UserModel::class)->findById($user->id)?->email);
    }

    public function testForcedResetOnlyReachesThePasswordScreen(): void
    {
        $user = $this->createUser();
        $user->forcePasswordReset();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('backend/profile/password'));
        $this->send('GET', 'backend/profile/sessions')->assertRedirectTo(site_url('backend/profile/password'));
        $this->send('GET', 'backend/profile/password')->assertSee(lang('Auth.msg.mustChangePassword'));

        $this->send('POST', 'backend/profile/password', [
            'current_password' => self::$password,
            'password'         => 'A-brand-new-passphrase-77',
            'password_confirm' => 'A-brand-new-passphrase-77',
        ]);

        $this->send('GET', 'backend/profile')->assertOK();
    }
}
