<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * Maintenance mode (session 27, used by restores): every request answers 503 with Retry-After
 * and a page that reads no database, except the paths the mode allows; JSON for API clients;
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

        $this->assertStringContainsString(lang('Core.maintenance.title'), (string) $this->call('GET', '/')->response()->getBody());
        $this->assertSame(lang('Core.maintenance.title'), json_decode((string) $this->call('GET', 'api/v1/news')->response()->getBody(), true)['error'] ?? null);

        $this->assertTrue($mode->allows('progress/backup/0123'));
        $this->assertFalse($mode->allows('progress/other'));
        $this->assertFalse($mode->allows('backend/backups'));

        $mode->disable();
        $this->assertFalse($mode->isOn());
        $this->assertNull($mode->state());
    }

    public function testTheSchedulerSkipsWhileItIsOn(): void
    {
        (new MaintenanceMode())->enable('test');

        command('schedule:run');

        $this->assertStringContainsString(lang('Core.maintenance.skipped'), $this->getStreamFilterBuffer());
    }
}
