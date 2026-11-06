<?php

declare(strict_types=1);

namespace Modules\Activity\Tests;

use Modules\Api\Tests\ApiTestCase;
use Modules\Auth\Services\PasswordChanger;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Permissions\GrantManager;

/**
 * The writes and security events of sessions 05–11 reach the activity log with the right user,
 * module and type: password and 2FA changes, grants, module toggles, login throttling, app
 * logins, token use from a new address, notification preferences, re-authentication.
 *
 * @internal
 */
final class ActivityEventsTest extends ApiTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function last(string $action): array
    {
        $row = db_connect()->table('activity_log')->where('action', $action)->orderBy('id', 'desc')->get(1)->getRowArray();
        $this->assertIsArray($row, "No \"{$action}\" entry.");

        return $row;
    }

    public function testAccountSecurityChanges(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');

        (new PasswordChanger())->change($ana, 'Another-long-password-2026');
        $entry = $this->last('password_changed');
        $this->assertSame(['security', 'Auth', (int) $ana->id], [$entry['type'], $entry['module'], (int) $entry['record_id']]);
        $this->assertStringNotContainsString('Another-long', (string) $entry['changes']);

        (new TwoFactor())->enableEmail($ana);
        $this->assertSame((int) $ana->id, (int) $this->last('2fa_enabled')['user_id']);

        (new TwoFactor())->reset($ana, 99);
        $reset = $this->last('2fa_reset');
        $this->assertSame([99, (string) $ana->id], [(int) $reset['user_id'], $reset['record_id']]);
    }

    public function testGrantsAndModulesChangesAreLogged(): void
    {
        $boss = $this->createUser('boss@example.com', 'superadmin');

        (new GrantManager())->setGroupGrants($boss, 'editor', ['api.tokens', 'activity-mine.*', 'core-notifications.*', 'auth-profile.*', 'auth-sessions.*', 'api.docs']);
        $grants = $this->last('grants_changed');
        $this->assertSame([(int) $boss->id, 'editor', 'Core'], [(int) $grants['user_id'], $grants['record_id'], $grants['module']]);
        $this->assertStringContainsString('api.docs', (string) $grants['changes']);

        (new ModuleToggler())->disable('Activity');
        $this->assertSame('Activity', $this->last('module_disabled')['summary']);
        (new ModuleToggler())->enable('Activity');
        $this->assertSame('Activity', $this->last('module_enabled')['summary']);
    }

    public function testLoginThrottlingIsASecurityEntry(): void
    {
        $this->createUser('ana@example.com', 'user');

        for ($i = 0; $i < 12; $i++) {
            $this->login('ana@example.com', 'wrong-' . $i);
        }

        $entry = $this->last('throttled');
        $this->assertSame(['security', 'Auth', '/login'], [$entry['type'], $entry['module'], $entry['summary']]);
    }

    public function testAppLoginsAndTokensFromANewAddress(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');

        $this->api('POST', 'api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'wrong', 'device_name' => 'Phone']);
        $failed = $this->last('api_login_failed');
        $this->assertSame(['security', 'Api', 'ana@example.com'], [$failed['type'], $failed['module'], $failed['summary']]);

        $token = $this->tokenFor($ana);
        $this->api('GET', 'api/v1/me', null, $token)->assertStatus(200);
        service('superglobals')->setServer('REMOTE_ADDR', '198.51.100.7');
        $this->api('GET', 'api/v1/me', null, $token)->assertStatus(200);

        $entry = $this->last('token_new_ip');
        $this->assertSame((int) $ana->id, (int) $entry['user_id']);
        $this->assertSame(['before' => ['ip' => '192.0.2.10'], 'after' => ['ip' => '198.51.100.7']], json_decode((string) $entry['changes'], true));
    }

    public function testProfileAndPreferencesChanges(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile/notifications', ['types' => []]);
        $this->assertSame((int) $ana->id, (int) $this->last('preferences_saved')['user_id']);

        $this->send('POST', 'backend/profile', [
            'name' => "Ana\nForged", 'email' => 'ana@example.com', 'locale' => 'ca', 'timezone' => 'Europe/Madrid', 'theme_mode' => 'dark', 'birth_date' => '',
        ]);
        $entry   = $this->last('profile_updated');
        $changes = json_decode((string) $entry['changes'], true);
        $this->assertSame('dark', $changes['after']['theme_mode']);
        $this->assertSame((int) $ana->id, (int) $entry['user_id']);
    }
}
