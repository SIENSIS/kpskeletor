<?php

declare(strict_types=1);

namespace Modules\Updates\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Config\Version;
use Modules\Core\Controllers\BackendController;
use Modules\Updates\Config\Updates as UpdatesConfig;
use Modules\Updates\Services\ReleaseChecker;
use Modules\Updates\Services\ReleaseNotes;
use Modules\Updates\Services\UpdateStatus;

/**
 * The Updates page (`backend/updates`, `updates.view`): shows, never applies. Versions come from
 * the last check (UpdateStatus); "check now" (`updates.check`) runs ReleaseChecker on demand.
 */
final class Updates extends BackendController
{
    public function index(): string
    {
        $status = (new UpdateStatus())->read();
        $core   = is_array($status['core'] ?? null) ? $status['core'] : [];
        $latest = is_array($core['latest'] ?? null) ? $core['latest'] : null;

        $this->breadcrumb(lang('Updates.title'));

        return $this->render('Modules\Updates\Views\index', [
            'title'      => lang('Updates.title'),
            'icon'       => 'bi bi-cloud-arrow-down',
            'installed'  => Version::CORE,
            'repository' => (string) config(UpdatesConfig::class)->repository,
            'signed'     => config(UpdatesConfig::class)->keys() !== [],
            'latest'     => $latest,
            'notes'      => $latest !== null ? ReleaseNotes::toHtml((string) ($latest['notes'] ?? '')) : '',
            'newer'      => ReleaseChecker::newer($status),
            'modules'    => is_array($status['modules'] ?? null) ? $status['modules'] : [],
            'checkedAt'  => is_string($status['checkedAt'] ?? null) ? $status['checkedAt'] : null,
            'error'      => is_string($status['error'] ?? null) ? $status['error'] : null,
            'canCheck'   => $this->can('updates.check'),
        ]);
    }

    public function check(): RedirectResponse
    {
        @set_time_limit(120);
        $status = service('updatechecker')->check();
        $back   = redirect()->to(backend_url('updates'));

        if (is_string($status['error'] ?? null)) {
            return $back->with('error', lang('Updates.msg.checkFailed', [$status['error']]));
        }

        $newer = ReleaseChecker::newer($status);

        return $back->with('success', $newer === [] ? lang('Updates.msg.upToDate') : lang('Updates.msg.available', [implode(', ', array_map(static fn (string $name, string $version): string => $name . ' ' . $version, array_keys($newer), $newer))]));
    }
}
