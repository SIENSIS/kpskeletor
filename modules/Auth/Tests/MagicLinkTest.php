<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Services\LinkTokens;

/**
 * Magic-link login: the same answer for known and unknown addresses, hashed single-use tokens,
 * expiry, and the rate limits (429 per IP, silent per account).
 *
 * @internal
 */
final class MagicLinkTest extends AuthTestCase
{
    public function testKnownAndUnknownAddressesGetTheSameAnswer(): void
    {
        $this->createUser();

        $known   = $this->send('POST', 'login/magic-link', ['email' => 'ana@example.com'], []);
        $unknown = $this->send('POST', 'login/magic-link', ['email' => 'nobody@example.com'], []);

        $known->assertOK();
        $unknown->assertOK();
        $known->assertSee(lang('Auth.msg.linkSent'));
        $unknown->assertSee(lang('Auth.msg.linkSent'));
        $this->assertCount(1, $this->sentEmails, 'Only the existing account gets an email.');
        $this->assertSame(['ana@example.com'], $this->lastEmail()['recipients'] ?? null);
    }

    public function testTokenIsStoredHashedAndWorksOnce(): void
    {
        $user = $this->createUser();
        $this->send('POST', 'login/magic-link', ['email' => 'ana@example.com'], []);
        $token = $this->linkToken('login/verify-magic-link');

        $identity = model(UserIdentityModel::class)->where('user_id', $user->id)->where('type', LinkTokens::MAGIC_LINK)->first();
        $this->assertNotNull($identity);
        $this->assertSame(hash('sha256', $token), $identity->secret);
        $this->assertNotSame($token, $identity->secret);

        $this->send('GET', 'login/verify-magic-link?token=' . $token, [], [])->assertRedirectTo(site_url('backend/profile'));
        $this->assertSame((int) $user->id, $this->loggedInUserId());

        $this->send('GET', 'login/verify-magic-link?token=' . $token, [], [])->assertRedirectTo(site_url('login/magic-link'));
        $this->assertNull($this->loggedInUserId(), 'A used link does not work again.');
    }

    public function testExpiredTokenIsRefused(): void
    {
        $user  = $this->createUser();
        $token = (new LinkTokens())->issue($user, LinkTokens::MAGIC_LINK);

        model(UserIdentityModel::class)->where('user_id', $user->id)->where('type', LinkTokens::MAGIC_LINK)
            ->set(['expires' => Time::now()->subMinutes(1)->format('Y-m-d H:i:s')])->update();

        $this->send('GET', 'login/verify-magic-link?token=' . $token, [], [])->assertRedirectTo(site_url('login/magic-link'));
        $this->assertNull($this->loggedInUserId());
    }

    public function testPerAccountLimitIsSilent(): void
    {
        config('Auth')->emailLinksPerAccount = 2;
        $this->createUser();

        foreach (range(1, 4) as $i) {
            service('superglobals')->setServer('REMOTE_ADDR', '192.0.2.' . $i);
            $this->send('POST', 'login/magic-link', ['email' => 'ana@example.com'], [])->assertSee(lang('Auth.msg.linkSent'));
        }

        $this->assertCount(2, $this->sentEmails);
    }

    public function testPerIpLimitAnswers429(): void
    {
        config('Auth')->emailLinksPerIp = 2;

        $this->send('POST', 'login/magic-link', ['email' => 'a@example.com'], [])->assertOK();
        $this->send('POST', 'reset-password', ['email' => 'b@example.com'], [])->assertOK();
        $this->send('POST', 'login/magic-link', ['email' => 'c@example.com'], [])->assertStatus(429);
    }

    public function testBannedAccountGetsNoEmail(): void
    {
        $this->createUser()->ban();

        $this->send('POST', 'login/magic-link', ['email' => 'ana@example.com'], [])->assertSee(lang('Auth.msg.linkSent'));
        $this->assertSame([], $this->sentEmails);
    }

    public function testMalformedTokensAreRefusedAndCounted(): void
    {
        config('Auth')->loginAttemptsPerIp = 2;

        $this->send('GET', 'login/verify-magic-link?token=abc', [], [])->assertRedirectTo(site_url('login/magic-link'));
        $this->send('GET', 'login/verify-magic-link?token=' . str_repeat('a', 48), [], [])->assertRedirectTo(site_url('login/magic-link'));
        $this->send('GET', 'login/verify-magic-link?token=' . str_repeat('b', 48), [], [])->assertStatus(429);
    }
}
