<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Maintenance\MaintenancePage;

/**
 * Maintenance mode (session 27, used by restores): every request answers 503 with Retry-After
 * and the theme's maintenance page (D-28-07), except the paths the mode allows; JSON for API clients;
 * `schedule:run` does nothing meanwhile.
 *
 * @internal
 */
final class MaintenanceModeTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use StreamFilterTrait;

    protected function tearDown(): void
    {
        (new MaintenanceMode())->disable();
        parent::tearDown();
    }

    public function testEveryRequestAnswers503ExceptTheAllowedPaths(): void
    {
        $mode = new MaintenanceMode();
        $mode->enable('test', ['progress/backup/*']);

        $this->assertTrue($mode->isOn());
        $this->assertSame('test', $mode->state()['reason'] ?? null);
        $this->assertSame(0640, fileperms($mode->file()) & 0777);

        foreach (['/', 'backend', 'login', 'install', 'api/v1/news'] as $path) {
            $response = $this->call('GET', $path);
            $response->assertStatus(503);
            $this->assertSame('60', $response->response()->getHeaderLine('Retry-After'), $path);
        }

        $page = (string) $this->call('GET', '/')->response()->getBody();
        $this->assertStringContainsString(lang('Core.maintenance.title'), $page);
        $this->assertStringContainsString('class="error-page maintenance-page', $page, 'Drawn by the theme, like the other error pages.');
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*themes/default/#', $page);
        $this->assertStringContainsString(lang('Core.maintenance.retry'), $page);
        $this->assertSame(lang('Core.maintenance.title'), json_decode((string) $this->call('GET', 'api/v1/news')->response()->getBody(), true)['error'] ?? null);

        $this->assertTrue($mode->allows('progress/backup/0123'));
        $this->assertFalse($mode->allows('progress/other'));
        $this->assertFalse($mode->allows('backend/backups'));

        $mode->disable();
        $this->assertFalse($mode->isOn());
        $this->assertNull($mode->state());
    }

    public function testThePlainFallbackNeedsNothing(): void
    {
        $page = MaintenancePage::plain('Back <soon>');

        $this->assertStringContainsString('Back &lt;soon&gt;', $page);
        $this->assertStringNotContainsString('<link', $page);
        $this->assertStringContainsString(lang('Core.maintenance.message'), MaintenancePage::plain());
    }

    public function testTheSchedulerSkipsWhileItIsOn(): void
    {
        (new MaintenanceMode())->enable('test');

        command('schedule:run');

        $this->assertStringContainsString(lang('Core.maintenance.skipped'), $this->getStreamFilterBuffer());
    }
}
