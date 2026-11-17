<?php

declare(strict_types=1);

namespace Modules\Core\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\Core\Security\Check\ComposerAudit;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityStatus;

/**
 * Daily security check: runs `composer audit` (result stored with its date), then every check
 * (without the external probe), stores the results for the Security status page and its widget,
 * and notifies the superadmins when a check turns red. The run itself succeeds (its output lists
 * the checks in error); only a crash fails it.
 */
final class SecurityCheckTask implements TaskInterface
{
    public function __construct(
        private readonly ?ComposerAudit $audit = null,
        private readonly ?SecurityChecker $checker = null,
        private readonly ?SecurityStatus $status = null,
    ) {
    }

    public function name(): string
    {
        return lang('SecurityCheck.task');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('04:43');
    }

    public function run(): TaskResult
    {
        $audit  = ($this->audit ?? new ComposerAudit())->run();
        $report = ($this->checker ?? new SecurityChecker())->run();
        ($this->status ?? new SecurityStatus())->store($report);

        $counts = $report->counts();
        $output = lang('SecurityCheck.summary', [$counts['ok'], $counts['info'], $counts['warning'], $counts['error']]);

        if (is_string($audit['error'] ?? null)) {
            $output .= "\n" . lang('SecurityCheck.audit.failed', [(string) $audit['checkedAt'], $audit['error']]);
        }

        // Errors are reported by SecurityStatus (one notification when a check turns red), not by
        // failing the run every day.
        return TaskResult::success($report->hasErrors() ? $output . "\n" . implode(', ', $report->errorIds()) : $output);
    }
}
