<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\StreamFilterTrait;
use Config\ProductionCheck as AppProductionCheck;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Notifications\Types\SecurityCheckFailed;
use Modules\Core\Scheduler\TaskResult;
use Modules\Core\Security\Check\ComposerAudit;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityReport;
use Modules\Core\Security\Check\SecurityStatus;
use Modules\Core\Tasks\SecurityCheckTask;
use Modules\Dashboard\Tests\DashboardTestCase;

/**
 * The Security status page (`backend/security`, `security.check`): permissions and forged POSTs,
 * the same results as `security:check`, the effective CSP with an origin added in Settings ›
 * Security, the probe behind re-authentication, the dashboard widget, the daily task and the
 * notification to superadmins when a check turns red (acceptance criterion 12).
 *
 * @internal
 */
final class SecurityStatusScreenTest extends DashboardTestCase
{
    use StreamFilterTrait;

    protected array $hiddenWidgets = [];

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir    = sys_get_temp_dir() . '/kp-security-screen-' . bin2hex(random_bytes(4));
        $config       = new AppProductionCheck();
        $config->path = $this->dir;
        Factories::injectMock('config', ProductionCheck::class, $config);
        Factories::injectMock('config', 'ProductionCheck', $config);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testThePageAndItsActionsNeedSecurityCheck(): void
    {
        $this->loginWith();
        $this->send('GET', 'backend/security')->assertStatus(403);
        $this->send('POST', 'backend/security/refresh')->assertStatus(403);
        $this->send('POST', 'backend/security/probe')->assertStatus(403);
        $this->send('GET', 'backend/security/probe')->assertStatus(403);
        $this->assertFileDoesNotExist($this->dir . '/status.json', 'A forged POST runs nothing.');
        $this->assertFileDoesNotExist($this->dir . '/probe.json');
    }

    public function testAdminsSeeTheSameResultsAsTheCommand(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $page = (string) $this->send('GET', 'backend/security')->getBody();
        $this->assertFileExists($this->dir . '/status.json', 'The first visit runs and stores the checks.');

        $report = (new SecurityStatus())->read();
        $this->assertInstanceOf(SecurityReport::class, $report);
        $this->assertStringContainsString('data-check="configuration.environment" data-status="error"', $page, 'The test environment is not production.');

        foreach ($report->results() as $result) {
            $this->assertStringContainsString('data-check="' . $result->id . '" data-status="' . $result->status . '"', $page, $result->id);
        }

        $this->assertStringContainsString('data-effective-csp', $page);
        $this->assertStringContainsString('X-Content-Type-Options', $page);
        $this->assertStringContainsString('Not run yet', $page, 'No probe without a deliberate action.');

        // `security:check` refreshes the same stored results the page shows.
        command('security:check --only=configuration');
        $this->assertStringContainsString(lang('SecurityCheck.environment.title'), $this->getStreamFilterBuffer());
        $this->assertStringNotContainsString(lang('SecurityCheck.lock.title'), $this->getStreamFilterBuffer());
    }

    public function testAnOriginAddedInSettingsAppearsInTheEffectiveCsp(): void
    {
        setting('Core.cspImgSrc', ['https://images.example.org']);
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $page = (string) $this->send('GET', 'backend/security')->getBody();
        $this->assertMatchesRegularExpression('#<strong>img-src</strong>[^<]*https://images\.example\.org#', $page);
    }

    public function testRefreshIsLoggedAndTheProbeNeedsARecentReauthentication(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->send('POST', 'backend/security/refresh')->assertRedirectTo(site_url('backend/security'));
        $this->assertFileExists($this->dir . '/status.json');
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'security_check')->countAllResults());

        $this->send('GET', 'backend/security/probe')->assertStatus(200);

        Time::setTestNow(Time::now()->addMinutes(16));
        $this->send('GET', 'backend/security/probe')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/security/probe')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertFileDoesNotExist($this->dir . '/probe.json');
        Time::setTestNow();
    }

    public function testTheWidgetShowsTheLastRun(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $before = (string) $this->send('GET', 'backend')->getBody();
        $this->assertStringContainsString('data-widget="core.security-status"', $before);
        $this->assertStringContainsString(esc(lang('SecurityCheck.widget.never')), $before);
        $this->assertFileDoesNotExist($this->dir . '/status.json', 'The dashboard never runs the checks.');

        $report = new SecurityReport(gmdate('Y-m-d H:i:s'));
        $report->error('secrets', 'secrets.maskKey', 'Mask key');
        (new SecurityStatus())->store($report, false);

        $after = (string) $this->send('GET', 'backend')->getBody();
        $this->assertStringContainsString('data-security-widget="error"', $after);
        $this->assertStringContainsString('Mask key', $after);

        $_SESSION      = [];
        $this->cookies = [];
        $this->createUser('editor@example.com', 'editor');
        $this->login('editor@example.com');
        $this->assertStringNotContainsString('core.security-status', (string) $this->send('GET', 'backend')->getBody());
    }

    public function testSuperadminsAreNotifiedOnceWhenACheckTurnsRed(): void
    {
        $root = $this->createUser('root@example.com', 'superadmin');
        $this->createUser('admin@example.com', 'admin');
        $status = new SecurityStatus();

        $ok = new SecurityReport('2026-11-17 01:00:00');
        $ok->ok('secrets', 'secrets.maskKey', 'Mask key');
        $this->assertSame([], $status->store($ok));

        $red = new SecurityReport('2026-11-17 02:00:00');
        $red->error('secrets', 'secrets.maskKey', 'Mask key');
        $this->assertSame(['secrets.maskKey'], $status->store($red));
        $this->assertSame([], $status->store($red), 'Still red: no new notification.');

        $rows = db_connect()->table('notifications')->where('type', SecurityCheckFailed::class)->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $root->id, (int) $rows[0]['user_id']);
    }

    public function testTheDailyTaskRunsTheAuditAndStoresTheResults(): void
    {
        $config       = config(ProductionCheck::class);
        $audit        = new ComposerAudit($config, static fn (array $command): array => ['code' => 0, 'output' => '{"advisories": [], "abandoned": []}']);
        $result       = (new SecurityCheckTask($audit, new SecurityChecker()))->run();

        $this->assertSame(TaskResult::SUCCESS, $result->status);
        $this->assertSame(0, $audit->last()['advisories'] ?? null);
        $this->assertInstanceOf(SecurityReport::class, (new SecurityStatus())->read());
        $this->assertStringContainsString('configuration.environment', $result->output, 'Checks in error are listed.');
    }
}
