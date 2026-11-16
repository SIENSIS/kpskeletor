<?php

declare(strict_types=1);

namespace Modules\Updates\Widgets;

use Modules\Core\Config\Version;
use Modules\Core\Widgets\Widget;
use Modules\Core\Widgets\WidgetSizes;
use Modules\Updates\Services\ReleaseChecker;
use Modules\Updates\Services\UpdateStatus;

/**
 * Widget "System version": the installed version, the latest one known from the last check
 * (never a request to the repository: the dashboard makes no outbound call) and the modules
 * with a new version. Needs `updates.view`.
 */
final class SystemVersion extends Widget
{
    public function id(): string
    {
        return 'updates.version';
    }

    public function title(): string
    {
        return lang('Updates.widget.title');
    }

    public function icon(): string
    {
        return 'bi bi-cloud-arrow-down';
    }

    public function permission(): string
    {
        return 'updates.view';
    }

    public function sizes(): array
    {
        return [WidgetSizes::SMALL, WidgetSizes::MEDIUM];
    }

    public function render(array $options): string
    {
        $status = (new UpdateStatus())->read();
        $core   = is_array($status['core'] ?? null) ? $status['core'] : [];
        $newer  = ReleaseChecker::newer($status);

        return $this->view('Modules\Updates\Views\widget', [
            'installed' => Version::CORE,
            'latest'    => is_array($core['latest'] ?? null) ? (string) ($core['latest']['version'] ?? '') : null,
            'newer'     => $newer,
            'checkedAt' => is_string($status['checkedAt'] ?? null) ? $status['checkedAt'] : null,
            'error'     => is_string($status['error'] ?? null) ? $status['error'] : null,
            'url'       => site_url('backend/updates'),
        ]);
    }
}
