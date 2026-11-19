<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use Modules\Core\Security\Check\CheckResult;
use Modules\Core\Security\Check\SecurityStatus as Status;

/**
 * Core widget "Security status": the summary of the last security check run (the daily task,
 * `security:check` or the page; never run from the dashboard) and the checks in error, with a
 * link to the Security status page. Needs `security.check` (superadmin and admin by default).
 */
final class SecurityStatus extends Widget
{
    public function id(): string
    {
        return 'core.security-status';
    }

    public function title(): string
    {
        return lang('SecurityCheck.widget.title');
    }

    public function icon(): string
    {
        return 'bi bi-shield-check';
    }

    public function permission(): string
    {
        return 'security.check';
    }

    public function sizes(): array
    {
        return [WidgetSizes::SMALL, WidgetSizes::MEDIUM];
    }

    public function render(array $options): string
    {
        $report = (new Status())->read();
        $errors = [];

        foreach ($report?->results() ?? [] as $result) {
            if ($result->status === CheckResult::ERROR) {
                $errors[] = $result->title;
            }
        }

        return $this->view('Modules\Core\Views\widgets\security_status', [
            'status'    => $report?->status(),
            'counts'    => $report?->counts(),
            'errors'    => array_slice(array_values(array_unique($errors)), 0, 5),
            'checkedAt' => $report?->checkedAt,
            'url'       => backend_url('security'),
        ]);
    }
}
