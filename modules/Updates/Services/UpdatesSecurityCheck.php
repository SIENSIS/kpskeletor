<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Modules\Updates\Config\Updates as UpdatesConfig;

/**
 * The Updates module's part of `security:check` (event `securityChecks`), group `dependencies`:
 * a release repository without trusted signing keys (`updates.publicKeys`) is a warning, since
 * `app:update` then refuses every archive (and nothing proves who published a release).
 */
final class UpdatesSecurityCheck implements SecurityCheck
{
    public function __construct(private readonly ?UpdatesConfig $config = null)
    {
    }

    public function groups(): array
    {
        return ['dependencies'];
    }

    public function run(SecurityReport $report): void
    {
        $config = $this->config ?? config(UpdatesConfig::class);
        $title  = lang('Updates.securityCheck.title');

        if ($config->repository === '') {
            $report->info('dependencies', 'dependencies.updates', $title, lang('Updates.securityCheck.noRepository'));
        } elseif ($config->keys() === []) {
            $report->warning('dependencies', 'dependencies.updates', $title, lang('Updates.securityCheck.noKeys', [$config->repository]), lang('Updates.securityCheck.fix'));
        } else {
            $report->ok('dependencies', 'dependencies.updates', $title, lang('Updates.securityCheck.ok', [$config->repository, count($config->keys())]));
        }
    }
}
