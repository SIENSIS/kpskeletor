<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

/**
 * One group of `security:check` checks. The Core adds its own; modules add theirs from the
 * `securityChecks` event (`$checks->add(new MyChecks())`, docs/security.md). A check reads
 * the configuration and the database and never changes anything.
 */
interface SecurityCheck
{
    /**
     * Groups this check reports into (for `--only=<group>`).
     *
     * @return list<string>
     */
    public function groups(): array;

    public function run(SecurityReport $report): void;
}
