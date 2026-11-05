<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Config\Auth;
use Modules\Auth\Config\Module;
use Modules\Auth\Services\AuthPolicy;

/**
 * Password rules: Shield's local validators only, the minimum length setting copied into the
 * shared config('Auth') and enforced, the manifest defaults equal to Config\Auth.
 *
 * @internal
 */
final class PasswordPolicyTest extends AuthTestCase
{
    public function testOnlyLocalValidatorsAreUsed(): void
    {
        foreach (config('Auth')->passwordValidators as $validator) {
            $this->assertStringNotContainsString('Pwned', $validator, 'No outbound password check.');
        }

        $this->assertNotEmpty(config('Auth')->passwordValidators);
    }

    public function testStoredMinimumLengthIsCopiedIntoTheSharedConfig(): void
    {
        setting('Auth.minimumPasswordLength', 14);

        AuthPolicy::apply();

        $this->assertSame(14, config('Auth')->minimumPasswordLength);
    }

    public function testAWeakStoredValueIsRaisedToTheFloor(): void
    {
        setting('Auth.minimumPasswordLength', 4);

        AuthPolicy::apply();

        $this->assertSame(8, config('Auth')->minimumPasswordLength);
    }

    public function testAPasswordShorterThanTheSettingIsRefused(): void
    {
        setting('Auth.minimumPasswordLength', 16);
        $user = $this->createUser();
        $this->login('ana@example.com');

        // 15 characters: valid for Shield's default (8), refused with the setting (16).
        $response = $this->send('POST', 'backend/profile/password', [
            'current_password' => self::$password,
            'password'         => 'Qz7-unique-pass',
            'password_confirm' => 'Qz7-unique-pass',
        ]);

        $response->assertRedirectTo(site_url('backend/profile/password'));
        $this->assertArrayHasKey('password', $_SESSION['errors'] ?? []);
        $this->assertTrue(service('passwords')->verify(self::$password, (string) model(\CodeIgniter\Shield\Models\UserModel::class)->findById($user->id)?->password_hash));

        setting('Auth.minimumPasswordLength', 8);
        $r = $this->send('POST', 'backend/profile/password', [
            'current_password' => self::$password,
            'password'         => 'Qz7-unique-pass',
            'password_confirm' => 'Qz7-unique-pass',
        ]);
        fwrite(STDERR, print_r([$_SESSION['errors'] ?? null, $_SESSION['error'] ?? null, config('Auth')->minimumPasswordLength], true));
        $r->assertRedirectTo(site_url('backend/profile'));
    }

    public function testRememberLifetimeSettingDrivesShieldsRememberConfig(): void
    {
        setting('Auth.rememberLifetime', 3);
        AuthPolicy::apply();
        $this->assertSame(3 * DAY, config('Auth')->sessionConfig['rememberLength']);
        $this->assertTrue(config('Auth')->sessionConfig['allowRemembering']);

        setting('Auth.rememberLifetime', 0);
        AuthPolicy::apply();
        $this->assertFalse(config('Auth')->sessionConfig['allowRemembering']);
    }

    public function testManifestDefaultsMatchConfigAuth(): void
    {
        $config = new Auth();

        foreach ((new Module())->settings as $key => $definition) {
            $this->assertTrue(property_exists($config, $key), "Config\\Auth has no property {$key}.");
            $this->assertSame($definition['default'], $config->{$key}, "Default of Auth.{$key}.");
        }
    }

    public function testRegistrationIsOffAndMagicLinksOn(): void
    {
        $config = new Auth();

        $this->assertFalse($config->allowRegistration);
        $this->assertTrue($config->allowMagicLinkLogins);
        $this->assertSame(['email'], $config->validFields);
    }
}
