<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Security\Check\SecurityReport;
use Modules\Updates\Config\Updates as UpdatesConfig;
use Modules\Updates\Services\UpdatesSecurityCheck;

/**
 * The Updates module's part of `security:check`: a release repository without trusted signing
 * keys is a warning.
 *
 * @internal
 */
final class UpdatesSecurityCheckTest extends CIUnitTestCase
{
    public function testRepositoryWithoutKeysIsAWarning(): void
    {
        $config             = new UpdatesConfig();
        $config->repository = '';
        $this->assertSame('info', $this->checkStatus($config));

        $config->repository = 'github:acme/app';
        $config->publicKeys = '';
        $this->assertSame('warning', $this->checkStatus($config));

        $config->publicKeys = (new TestSigner())->publicKey();
        $this->assertSame('ok', $this->checkStatus($config));
    }

    private function checkStatus(UpdatesConfig $config): string
    {
        $report = new SecurityReport();
        (new UpdatesSecurityCheck($config))->run($report);

        return $report->results()[0]->status;
    }
}
