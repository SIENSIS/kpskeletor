<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * Maintenance mode turned on by hand (D-28-02): Settings › Actions (`settings.security` +
 * re-authentication, forged POSTs refused), the message on the 503 page, the login paths still
 * open, signed-in superadmins bypass it (with a banner) and nobody else does, a mode turned on
 * by a restore or an update is not turned off from the screen, `php spark app:maintenance`.
 *
 * @internal
 */
final class MaintenanceScreenTest extends SettingsTestCase
{
    use StreamFilterTrait;

    protected function tearDown(): void
    {
        (new MaintenanceMode())->disable();
        parent::tearDown();
    }

    /**
     * Feature tests use a mock session without a cookie: the filter looks for one first.
     */
    private function withSessionCookie(): void
    {
        $name = config('Cookie')->prefix . config('Session')->cookieName;

        if (! isset($this->cookies[$name])) {
            $this->cookies[$name] = 'test-session';
        }
    }

    public function testTurningItOnNeedsSettingsSecurity(): void
    {
        $this->loginWith('settings.update');

        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'on'])->assertStatus(403);
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertStringNotContainsString('actions/maintenance', (string) $this->send('GET', 'backend/settings/actions')->getBody());
    }

    public function testAnAdministratorTurnsItOnWithAMessageAndASuperadminBypassesIt(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->assertStringContainsString('actions/maintenance', (string) $this->send('GET', 'backend/settings/actions')->getBody());

        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'on', 'message' => "Back at 10:00\n<b>soon</b>"])->assertRedirectTo(site_url('backend/settings/actions'));

        $mode  = new MaintenanceMode();
        $state = $mode->state();
        $this->assertNotNull($state);
        $this->assertSame(MaintenanceMode::REASON_MANUAL, $state['reason']);
        $this->assertTrue($state['bypass']);
        $this->assertSame('Back at 10:00 <b>soon</b>', $state['message'], 'One line of plain text.');
        $this->assertCount(1, $this->entries('maintenance_on'));

        // The administrator (not a superadmin) gets the 503 page with the escaped message.
        $page = $this->send('GET', 'backend');
        $page->assertStatus(503);
        $this->assertStringContainsString('Back at 10:00 &lt;b&gt;soon&lt;/b&gt;', (string) $page->getBody());
        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'off'])->assertStatus(503);
        $this->assertTrue($mode->isOn());

        // Guests too, but the login stays open.
        $_SESSION      = [];
        $this->cookies = [];
        $this->send('GET', '/')->assertStatus(503);
        $this->send('GET', 'login')->assertOK();

        // A signed-in superadmin uses the site, sees the banner and turns it off.
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        $this->withSessionCookie();
        $actions = $this->send('GET', 'backend/settings/actions');
        $actions->assertOK();
        $this->assertStringContainsString('data-maintenance', (string) $actions->getBody());
        $this->assertStringContainsString(esc(lang('Core.maintenance.banner')), (string) $actions->getBody());

        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'off'])->assertRedirectTo(site_url('backend/settings/actions'));
        $this->assertFalse($mode->isOn());
        $this->assertCount(1, $this->entries('maintenance_off'));
        $this->send('GET', '/')->assertStatus(200);
    }

    public function testThePageIsThemedWithoutReadingStoredSettings(): void
    {
        service('settings')->set('Core.siteName', 'Name stored in the database');
        (new MaintenanceMode())->enableByHand('Back soon');

        $page = (string) $this->send('GET', '/')->getBody();

        $this->assertStringContainsString('class="error-page maintenance-page', $page);
        $this->assertStringContainsString('Back soon', $page);
        $this->assertStringNotContainsString('Name stored in the database', $page, 'Only the file configuration is read (the database may be being replaced).');
        $this->assertStringContainsString('<title>' . esc(lang('Core.maintenance.title')), $page);
    }

    public function testASuperadminWithoutASessionCookieIsNotLetThrough(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        (new MaintenanceMode())->enableByHand();
        unset($this->cookies[config('Cookie')->prefix . config('Session')->cookieName]);

        $this->send('GET', 'backend')->assertStatus(503);
    }

    public function testARestoreOrAnUpdateIsNotBypassedNorTurnedOffFromTheScreen(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        $this->withSessionCookie();

        $mode = new MaintenanceMode();
        $mode->enable('updates.update');

        $this->send('GET', 'backend/settings/actions')->assertStatus(503);
        $this->send('GET', 'login')->assertStatus(503);
        $this->assertTrue($mode->isOn());

        // Even if the screen is reached (an allowed path), "off" refuses a mode it did not turn on.
        $mode->enable('updates.update', ['backend/settings/*']);
        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'off']);
        $this->assertSame(lang('Settings.actions.maintenanceBusy'), $_SESSION['error'] ?? null);
        $this->assertTrue($mode->isOn());
        $this->send('POST', 'backend/settings/actions/maintenance', ['state' => 'on']);
        $this->assertSame('updates.update', $mode->state()['reason'] ?? null);
    }

    public function testTheConsoleCommand(): void
    {
        $mode = new MaintenanceMode();

        command('app:maintenance on --message "Upgrading the server"');
        $this->assertTrue($mode->isOn());
        $state = $mode->state();
        $this->assertNotNull($state);
        $this->assertSame('Upgrading the server', $state['message']);
        $this->assertContains('login', $state['allow']);

        command('app:maintenance status');
        $this->assertStringContainsString('manual', $this->getStreamFilterBuffer());

        command('app:maintenance off');
        $this->assertFalse($mode->isOn());
        $this->assertCount(1, $this->entries('maintenance_on'));
        $this->assertCount(1, $this->entries('maintenance_off'));
    }
}
