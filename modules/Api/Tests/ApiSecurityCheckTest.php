<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Api\Services\ApiSecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * The Api module's part of `security:check`: CORS never open to `*`, personal tokens without expiry.
 *
 * @internal
 */
final class ApiSecurityCheckTest extends CIUnitTestCase
{
    public function testCorsAndPersonalTokens(): void
    {
        $report = new SecurityReport();
        (new ApiSecurityCheck(['*'], 2))->run($report);
        $this->assertSame(['configuration.cors'], $report->errorIds());
        $this->assertSame('info', $this->statuses($report)['accounts.personalTokens']);

        $report = new SecurityReport();
        (new ApiSecurityCheck(['https://app.example.com'], 0))->run($report);
        $this->assertSame([], $report->errorIds());
        $this->assertSame('ok', $this->statuses($report)['accounts.personalTokens']);
    }

    /**
     * @return array<string, string> id => status
     */
    private function statuses(SecurityReport $report): array
    {
        $statuses = [];

        foreach ($report->results() as $result) {
            $statuses[$result->id] = $result->status;
        }

        return $statuses;
    }
}
