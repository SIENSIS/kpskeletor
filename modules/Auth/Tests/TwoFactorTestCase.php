<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Config\Services;
use Modules\Auth\Services\TwoFactor;

/**
 * Base of the 2FA and re-authentication tests: an encryption key for the TOTP secrets and
 * helpers to enrol users and read the codes sent by email.
 */
abstract class TwoFactorTestCase extends AuthTestCase
{
    /** A fixed moment in the middle of a 30-second TOTP step. */
    protected const NOW = 1_800_000_015;

    protected function setUp(): void
    {
        parent::setUp();
        config('Encryption')->key = str_repeat("\x42", 32);
        Services::resetSingle('encrypter');
        Time::setTestNow(Time::createFromTimestamp(self::NOW));
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    /**
     * Enrols the user with the authenticator app; returns [secret, recovery codes].
     *
     * @return array{0: string, 1: list<string>}
     */
    protected function enrolTotp(User $user): array
    {
        $twoFactor = new TwoFactor();
        $secret    = $twoFactor->totp()->newSecret();
        // The enrolment code uses the previous step, so the current one is still unused.
        $codes = $twoFactor->enableTotp($user, $secret, $this->totpCode($secret, -1));
        $this->assertNotNull($codes);

        return [$secret, $codes];
    }

    protected function totpCode(string $secret, int $stepOffset = 0): string
    {
        return (new TwoFactor())->totp()->codeAt($secret, Time::now()->getTimestamp() + $stepOffset * 30);
    }

    /**
     * The 6-digit code of the last email.
     */
    protected function emailedCode(): string
    {
        $email = $this->lastEmail();
        $this->assertNotNull($email, 'No email was sent.');
        $this->assertSame(1, preg_match('#<strong>(\d{6})</strong>#', (string) $email['body'], $match));

        return $match[1];
    }

    protected function isPending(): bool
    {
        $info = $_SESSION['user'] ?? null;

        return is_array($info) && ! empty($info['auth_action']);
    }
}
