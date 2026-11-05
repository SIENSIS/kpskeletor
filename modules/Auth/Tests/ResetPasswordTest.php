<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Models\UserSessionModel;
use Modules\Auth\Services\PasswordChanger;

/**
 * Password reset by email: generic answer, single-use link, strong password, every session
 * ended and the app tokens revoked (personal tokens stay).
 *
 * @internal
 */
final class ResetPasswordTest extends AuthTestCase
{
    public function testResetFlowChangesThePasswordAndEndsEverything(): void
    {
        $user = $this->createUser();
        $this->login('ana@example.com', null, true);
        $this->assertSame(1, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults());

        $app      = $user->generateAccessToken('Phone app');
        $personal = $user->generateAccessToken('My script');
        model(UserIdentityModel::class)->update($personal->id, ['secret2' => PasswordChanger::PERSONAL_TOKEN]);

        $this->cookies = [];
        $this->send('POST', 'reset-password', ['email' => 'ana@example.com'], [])->assertSee(lang('Auth.msg.linkSent'));
        $token = $this->linkToken('reset-password/new');

        $this->send('GET', 'reset-password/new?token=' . $token, [], [])->assertOK();

        $response = $this->send('POST', 'reset-password/new', [
            'token'            => $token,
            'password'         => 'A-brand-new-passphrase-77',
            'password_confirm' => 'A-brand-new-passphrase-77',
        ], []);

        $response->assertRedirectTo(site_url('login'));
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertTrue(service('passwords')->verify('A-brand-new-passphrase-77', (string) $fresh?->password_hash));

        $this->assertSame(0, model(UserSessionModel::class)->where('user_id', $user->id)->where('ended_at', null)->countAllResults(), 'Every session ended.');
        $this->assertSame(0, db_connect()->table('auth_remember_tokens')->where('user_id', $user->id)->countAllResults(), 'Remember-me tokens deleted.');

        $names = array_map(static fn ($t): string => (string) $t->name, $fresh?->accessTokens() ?? []);
        $this->assertSame(['My script'], $names, 'App tokens revoked, personal token kept.');
        $this->assertNotNull($app->id);

        $this->send('POST', 'reset-password/new', [
            'token'            => $token,
            'password'         => 'Another-new-passphrase-88',
            'password_confirm' => 'Another-new-passphrase-88',
        ], [])->assertRedirectTo(site_url('reset-password'));
    }

    public function testWeakPasswordIsRefusedAndTheLinkStaysValid(): void
    {
        $this->createUser();
        $this->send('POST', 'reset-password', ['email' => 'ana@example.com'], []);
        $token = $this->linkToken('reset-password/new');

        $response = $this->send('POST', 'reset-password/new', ['token' => $token, 'password' => 'short', 'password_confirm' => 'short'], []);

        $response->assertOK();
        $response->assertSee(lang('Auth.errorPasswordLength', [8]));
        $this->send('GET', 'reset-password/new?token=' . $token, [], [])->assertOK();
    }

    public function testInvalidLinkIsRefused(): void
    {
        $this->send('GET', 'reset-password/new?token=' . str_repeat('c', 48), [], [])->assertRedirectTo(site_url('reset-password'));
    }

    public function testUnknownAddressGetsTheSameAnswer(): void
    {
        $this->send('POST', 'reset-password', ['email' => 'nobody@example.com'], [])->assertSee(lang('Auth.msg.linkSent'));
        $this->assertSame([], $this->sentEmails);
    }
}
