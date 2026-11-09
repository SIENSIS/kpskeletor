<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use Config\Auth;
use Config\Services;
use Modules\Auth\Models\UserSessionModel;

/**
 * The settings screens: `settings.update` opens them, `settings.<module>` is needed to save a
 * module's tab (also against forged POSTs), values are validated and cast to their type, only
 * overrides are stored, and every change is logged.
 *
 * @internal
 */
final class SettingsScreensTest extends SettingsTestCase
{
    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    public function testWithoutSettingsUpdateEveryScreenIsForbidden(): void
    {
        $this->loginWith();

        foreach (['backend/settings', 'backend/settings/core', 'backend/settings/theme', 'backend/settings/actions', 'backend/settings/security'] as $path) {
            $this->send('GET', $path)->assertStatus(403);
        }

        $this->send('POST', 'backend/settings/core', ['Core-siteName' => 'Forged'])->assertStatus(403);
        $this->assertSame(0, $this->storedCount('Core', 'siteName'));
    }

    public function testSettingsUpdateOpensTheTabsReadOnly(): void
    {
        $this->loginWith('settings.update');

        $this->send('GET', 'backend/settings')->assertRedirectTo(site_url('backend/settings/core'));

        $page = $this->send('GET', 'backend/settings/core');
        $page->assertOK();
        $page->assertSee(lang('Settings.ui.readOnly'));
        $this->assertMatchesRegularExpression('/name="Core-siteName"[^>]*disabled/', (string) $page->getBody());
        $page->assertDontSee(lang('Settings.tabs.security'));
    }

    public function testSavingAModuleTabNeedsItsOwnPermission(): void
    {
        $this->loginWith('settings.update');

        $this->send('POST', 'backend/settings/core', ['Core-siteName' => 'Forged', 'Core-timezone' => 'UTC'])->assertStatus(403);

        $this->assertSame(0, $this->storedCount('Core', 'siteName'));
        $this->assertSame('settings.core', json_decode((string) $this->entries('forbidden')[0]['changes'], true)['changes']['permissions'][0]);
    }

    public function testAModuleTabIsSavedCastAndLogged(): void
    {
        $user = $this->loginWith('settings.update', 'settings.core');

        $this->send('POST', 'backend/settings/core', [
            'Core-siteName'     => '  Institut Demo  ',
            'Core-timezone'     => 'Europe/Madrid',
            'Core-pwa'          => '1',
            'Core-pwaStart'     => 'backend',
            'Core-poorMansCron' => '0',
            'Core-widgetMaxLinks' => '20',
        ])->assertRedirectTo(site_url('backend/settings/core'));

        $this->assertSame('Institut Demo', setting('Core.siteName'));
        $this->assertSame('Institut Demo', site_name());
        $this->assertSame('Europe/Madrid', site_timezone());
        $this->assertTrue(setting('Core.pwa'));
        $this->assertSame('backend', setting('Core.pwaStart'));
        $this->assertSame(0, $this->storedCount('Core', 'poorMansCron'), 'A value equal to the default is not stored.');

        $entry = $this->entries('settings_changed');
        $this->assertCount(1, $entry);
        $this->assertSame([(int) $user->id, 'Settings', 'Core'], [(int) $entry[0]['user_id'], $entry[0]['module'], $entry[0]['summary']]);
        $this->assertSame(['Core.siteName' => 'Institut Demo', 'Core.timezone' => 'Europe/Madrid', 'Core.pwa' => true, 'Core.pwaStart' => 'backend'], json_decode((string) $entry[0]['changes'], true)['after']);

        $this->send('GET', 'backend/settings/core')->assertSee('Institut Demo');
    }

    public function testGoingBackToTheDefaultForgetsTheStoredValue(): void
    {
        $this->loginWith('settings.update', 'settings.core');
        setting('Core.siteName', 'Old name');

        $this->send('POST', 'backend/settings/core', ['Core-siteName' => '', 'Core-timezone' => 'UTC', 'Core-pwaStart' => 'public', 'Core-widgetMaxLinks' => '20']);

        $this->assertSame(0, $this->storedCount('Core', 'siteName'));
        $this->assertSame(lang('Core.app.name'), site_name());
    }

    public function testInvalidValuesAreRefusedAndNothingIsStored(): void
    {
        $this->loginWith('settings.update', 'settings.core');

        $page = $this->send('POST', 'backend/settings/core', ['Core-siteName' => 'Ok', 'Core-timezone' => 'Mars/Olympus', 'Core-pwaStart' => 'nowhere']);

        $page->assertStatus(422);
        $this->assertSame(0, $this->storedCount('Core', 'siteName'));
        $this->assertSame(0, $this->storedCount('Core', 'timezone'));
        $this->assertStringContainsString('is-invalid', (string) $page->getBody());
    }

    public function testSecuritySettingsAreNotOnTheModuleTabs(): void
    {
        $this->loginWith('settings.update', 'settings.core');

        // A rate limit posted to the Core tab is ignored: it belongs to Settings › Security.
        $this->send('POST', 'backend/settings/core', ['Core-timezone' => 'UTC', 'Core-pwaStart' => 'public', 'Core-rateLimitBackend' => '99999']);

        $this->assertSame(0, $this->storedCount('Core', 'rateLimitBackend'));

        // Auth only declares security settings: it has no tab of its own.
        $this->expectException(PageNotFoundException::class);
        $this->send('GET', 'backend/settings/auth');
    }

    public function testUnknownTabIsNotFound(): void
    {
        $this->loginWith('settings.update');

        $this->expectException(PageNotFoundException::class);
        $this->send('GET', 'backend/settings/nothing');
    }

    public function testSecurityNeedsItsPermissionAndARecentReauthentication(): void
    {
        $this->loginWith('settings.update');
        $this->send('GET', 'backend/settings/security')->assertStatus(403);
        $this->send('POST', 'backend/settings/security', ['Core-rateLimitBackend' => '5'])->assertStatus(403);
        $this->assertSame(0, $this->storedCount('Core', 'rateLimitBackend'));

        $_SESSION = [];
        $this->cookies = [];
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->send('GET', 'backend/settings/security')->assertOK();

        Time::setTestNow(Time::now()->addMinutes(16));
        $this->send('GET', 'backend/settings/security')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/settings/security', $this->securityForm(['Core-rateLimitBackend' => '5']))->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame(0, $this->storedCount('Core', 'rateLimitBackend'));
    }

    public function testSecuritySettingsAreSavedAndLoggedAsSecurityEntries(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/settings/security', $this->securityForm([
            'Core-rateLimitBackend' => '200',
            'Core-outboundHosts'    => "api.example.com\r\n*.example.org\r\napi.example.com",
            'Api-corsOrigins'       => 'https://app.example.com',
        ]))->assertRedirectTo(site_url('backend/settings/security'));

        $this->assertSame(200, setting('Core.rateLimitBackend'));
        $this->assertSame(['api.example.com', '*.example.org'], setting('Core.outboundHosts'));
        $this->assertSame(['https://app.example.com'], setting('Api.corsOrigins'));

        $entry = $this->entries('security_settings_changed');
        $this->assertCount(1, $entry);
        $this->assertSame('security', $entry[0]['type']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function weakValues(): iterable
    {
        yield 'unsafe-inline script' => ['Core-cspScriptSrc', "'unsafe-inline'"];
        yield 'wildcard script' => ['Core-cspScriptSrc', 'https://*.example.com'];
        yield 'any origin' => ['Core-cspImgSrc', '*'];
        yield 'scheme only' => ['Core-cspImgSrc', 'https:'];
        yield 'data images' => ['Core-cspImgSrc', 'data:'];
        yield 'plain http' => ['Core-cspFrameSrc', 'http://video.example.com'];
        yield 'keyword after a valid origin' => ['Core-cspStyleSrc', "https://cdn.example.com\n'unsafe-eval'"];
        yield 'outbound ip' => ['Core-outboundHosts', '127.0.0.1'];
        yield 'outbound localhost' => ['Core-outboundHosts', 'localhost'];
        yield 'outbound url' => ['Core-outboundHosts', 'https://api.example.com/x'];
        yield 'cors wildcard' => ['Api-corsOrigins', '*'];
        yield 'cors http' => ['Api-corsOrigins', 'http://app.example.com'];
        yield 'php uploads' => ['Core-uploadTypes', 'jpg,php'];
        yield 'html uploads' => ['Core-uploadTypes', 'html'];
        yield 'short passwords' => ['Auth-minimumPasswordLength', '6'];
        yield 'huge rate limit' => ['Core-rateLimitBackend', '100000'];
        yield 'no remember cap' => ['Auth-rememberLifetime', '9999'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weakValues')]
    public function testValuesThatWeakenTheBaselineAreRefused(string $field, string $value): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/settings/security', $this->securityForm([$field => $value, 'Core-rateLimitForms' => '7']))->assertStatus(422);

        $this->assertSame(0, db_connect()->table('settings')->whereIn('key', ['rateLimitForms', explode('-', $field, 2)[1]])->countAllResults(), 'Nothing is stored.');
        $this->assertCount(1, $this->entries('security_settings_refused'));
    }

    public function testMinimumPasswordLengthCannotGoBelowTheFileConfiguration(): void
    {
        $this->assertSame(8.0, \Modules\Core\Settings\SecurityRules::configValue('Auth.minimumPasswordLength'));
        $error = null;
        $rules = new \Modules\Core\Settings\SecurityRules();
        $this->assertFalse($rules->min_config('7', 'Auth.minimumPasswordLength', [], $error));
        $this->assertNotNull($error);
        $this->assertTrue($rules->min_config('12', 'Auth.minimumPasswordLength'));
    }

    public function testAChangedMinimumPasswordLengthIsEnforced(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->send('POST', 'backend/settings/security', $this->securityForm(['Auth-minimumPasswordLength' => '16']))->assertRedirect();

        // 15 characters: accepted by Shield's default (8), refused with the new setting.
        $this->send('POST', 'backend/profile/password', [
            'current_password' => self::$password,
            'password'         => 'Qz7-unique-pass',
            'password_confirm' => 'Qz7-unique-pass',
        ])->assertRedirectTo(site_url('backend/profile/password'));

        $this->assertSame(16, config(Auth::class)->minimumPasswordLength);
        $this->assertArrayHasKey('password', $_SESSION['errors'] ?? []);
    }

    public function testTheRememberMeLifetimeCapsRecreatedSessions(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com', null, true);
        $this->send('POST', 'backend/settings/security', $this->securityForm(['Auth-rememberLifetime' => '2']))->assertRedirect();
        $this->assertSame(2, setting('Auth.rememberLifetime'));

        // The session expired (absolute lifetime) and the remember-me token is 3 days old.
        model(UserSessionModel::class)->where('user_id', $admin->id)->where('ended_at', null)
            ->set(['created_at' => Time::now()->subMinutes(13 * 60)->format('Y-m-d H:i:s')])->update();
        db_connect()->table('auth_remember_tokens')->where('user_id', $admin->id)
            ->set(['created_at' => Time::now()->subDays(3)->format('Y-m-d H:i:s')])->update();

        $this->send('GET', 'backend/profile')->assertRedirectTo(site_url('login'));
        $this->assertNull($this->loggedInUserId());
    }

    public function testACspOriginAddedInTheUiAppearsInTheHeader(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/settings/security', $this->securityForm([
            'Core-cspImgSrc'    => 'https://images.example.com',
            'Core-cspFrameSrc'  => 'https://www.youtube-nocookie.com',
            'Core-cspScriptSrc' => 'https://cdn.example.com',
        ]))->assertRedirect();

        Services::resetSingle('csp');
        $response = $this->send('GET', 'backend/profile')->response();
        $response->getCSP()->finalize($response); // Response::send() does it; feature tests do not send
        $header = $response->getHeaderLine('Content-Security-Policy');

        $this->assertMatchesRegularExpression('#img-src [^;]*https://images\.example\.com#', $header);
        $this->assertMatchesRegularExpression('#frame-src [^;]*https://www\.youtube-nocookie\.com#', $header);
        $this->assertMatchesRegularExpression('#script-src [^;]*https://cdn\.example\.com#', $header);
        $this->assertStringNotContainsString('unsafe-inline', $header);
    }

    /**
     * Every field of Settings › Security with its current value, then the changes.
     *
     * @param array<string, string> $changes
     *
     * @return array<string, string>
     */
    private function securityForm(array $changes): array
    {
        $form = [];

        foreach ((new \Modules\Settings\Services\SettingsEditor())->securityFields() as $groups) {
            foreach ($groups as $fields) {
                foreach ($fields as $field) {
                    $form[$field->name] = $field->formValue();
                }
            }
        }

        return array_replace($form, $changes);
    }
}
