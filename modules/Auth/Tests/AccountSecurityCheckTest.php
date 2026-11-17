<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Auth\Services\AccountSecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * The Auth module's part of `security:check`: superadmins without 2FA, optional 2FA groups and the
 * minimum password length.
 *
 * @internal
 */
final class AccountSecurityCheckTest extends CIUnitTestCase
{
    public function testAccountChecks(): void
    {
        $report = new SecurityReport();
        (new AccountSecurityCheck(['root'], ['superadmin' => 'optional', 'admin' => 'required'], 8))->run($report);
        $statuses = $this->statuses($report);
        $this->assertSame('warning', $statuses['accounts.superadmin2fa']);
        $this->assertSame('info', $statuses['accounts.group2fa']);
        $this->assertSame('warning', $statuses['accounts.passwordLength']);

        $report = new SecurityReport();
        (new AccountSecurityCheck([], ['superadmin' => 'required'], 12))->run($report);
        $this->assertSame(['ok'], array_values(array_unique($this->statuses($report))));
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
