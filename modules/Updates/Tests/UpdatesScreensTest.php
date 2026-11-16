<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Http\OutboundException;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Scheduler\TaskResult;
use Modules\Updates\Config\Module;
use Modules\Updates\Notifications\NewVersion;
use Modules\Updates\Services\UpdateStatus;
use Modules\Updates\Tasks\CheckUpdates;

/**
 * The back office of Updates: permissions (forged POSTs included), acceptance criterion 9 (with
 * a newer tag in the configured repository the dashboard shows it after `updates:check`), the
 * page with safe release notes, one notification per version, network errors that never break
 * the dashboard, modules with their own repository, the task and the command.
 *
 * @internal
 */
final class UpdatesScreensTest extends UpdatesTestCase
{
    use StreamFilterTrait;

    public function testManifestAndGrants(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertSame('updates', $manifest->permissionPrefix('updates'));
        $this->assertSame(['admin' => ['updates.view', 'updates.check']], $manifest->defaultGrants);
        $this->assertContains('api.github.com', $manifest->outboundHosts);
    }

    public function testThePageAndTheCheckNeedTheirPermissions(): void
    {
        $this->publish([self::release('v9.0.0')]);

        $this->loginWith();
        $this->send('GET', 'backend/updates')->assertStatus(403);
        $this->send('POST', 'backend/updates/check')->assertStatus(403);

        $_SESSION      = [];
        $this->cookies = [];
        $this->createUser('viewer@example.com', 'user')->addPermission('updates.view');
        $this->login('viewer@example.com');
        $page = (string) $this->send('GET', 'backend/updates')->getBody();
        $this->assertStringContainsString(lang('Updates.noRelease'), $page);
        $this->assertStringNotContainsString('backend/updates/check', $page);
        $this->send('POST', 'backend/updates/check')->assertStatus(403);

        $this->assertSame([], $this->transport->sent, 'No outbound request without updates.check, and none to show the page.');
        $this->assertSame([], (new UpdateStatus())->read());
    }

    public function testTheDashboardShowsANewerTagAfterTheCheck(): void
    {
        $this->publish([self::release('v0.1.0'), self::release('v0.2.0', "## New\n- **Faster** pages\n<script>alert(1)</script>\n[Docs](https://example.com/docs) [bad](javascript:alert(1))")]);
        $this->createUser('admin@example.com', 'admin');
        $this->createUser('root@example.com', 'superadmin');
        $this->login('admin@example.com');

        $before = (string) $this->send('GET', 'backend')->getBody();
        $this->assertStringContainsString('data-widget="updates.version"', $before);
        $this->assertStringContainsString(lang('Updates.widget.unknown'), $before);
        $this->assertSame([], $this->transport->sent, 'The dashboard never calls the repository.');

        command('updates:check');
        $this->assertStringContainsString(lang('Updates.msg.available', ['Core 0.2.0']), $this->getStreamFilterBuffer());

        $after = (string) $this->send('GET', 'backend')->getBody();
        $this->assertStringContainsString(esc(lang('Updates.widget.available', ['0.2.0'])), $after);

        $page = (string) $this->send('GET', 'backend/updates')->getBody();
        $this->assertStringContainsString('<strong>Faster</strong>', $page);
        $this->assertStringContainsString('&lt;script&gt;', $page);
        $this->assertStringNotContainsString('<script>alert', $page);
        $this->assertStringContainsString('href="https://example.com/docs"', $page);
        $this->assertStringNotContainsString('href="javascript:', $page);
        $this->assertStringContainsString('php spark app:update --dry-run', $page);

        // Notified once per version, to the holders of updates.view (admin and superadmin).
        $this->assertSame(2, $this->notifications());
        $this->send('POST', 'backend/updates/check')->assertRedirectTo(site_url('backend/updates'));
        $this->assertSame(lang('Updates.msg.available', ['Core 0.2.0']), $_SESSION['success'] ?? null);
        $this->assertSame(2, $this->notifications());
        $this->assertNotEmpty(db_connect()->table('activity_log')->where('action', 'checked')->where('module', 'Updates')->get()->getResultArray());
    }

    public function testANetworkErrorNeverBreaksTheDashboard(): void
    {
        $this->transport->routes[self::RELEASES] = new OutboundException(OutboundException::REASON_TRANSPORT, 'Connection timed out.');
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/updates/check')->assertRedirectTo(site_url('backend/updates'));
        $this->assertStringContainsString('Connection timed out.', (string) ($_SESSION['error'] ?? ''));

        $dashboard = $this->send('GET', 'backend');
        $dashboard->assertOK();
        $this->assertStringContainsString(lang('Updates.widget.error'), (string) $dashboard->getBody());
        $this->send('GET', 'backend/updates')->assertOK();

        $result = (new CheckUpdates())->run();
        $this->assertSame(TaskResult::FAILED, $result->status);
        $this->assertSame(0, $this->notifications());
    }

    public function testModulesWithTheirOwnRepository(): void
    {
        $this->config->repository = '';
        $this->moduleRepositories = ['News' => ['version' => '0.1.0', 'repository' => 'github:acme/news']];
        $this->publish([self::release('v0.4.0')], 'https://api.github.com/repos/acme/news/releases?per_page=50');
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/updates/check');
        $page = (string) $this->send('GET', 'backend/updates')->getBody();
        $this->assertStringContainsString(lang('Updates.notConfigured'), $page);
        $this->assertStringContainsString('github:acme/news', $page);
        $this->assertStringContainsString(esc(lang('Updates.widget.modules', ['News 0.4.0'])), (string) $this->send('GET', 'backend')->getBody());
        $this->assertSame(1, $this->notifications());
    }

    public function testTheTask(): void
    {
        $this->publish([self::release('v0.1.0')]);
        $result = (new CheckUpdates())->run();

        $this->assertSame(TaskResult::SUCCESS, $result->status);
        $this->assertSame(lang('Updates.msg.upToDate'), $result->output);
        $this->assertSame(0640, fileperms((new UpdateStatus())->file()) & 0777);
    }

    private function notifications(): int
    {
        return db_connect()->table('notifications')->where('type', NewVersion::class)->countAllResults();
    }
}
