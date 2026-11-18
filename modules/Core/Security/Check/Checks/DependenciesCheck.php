<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use Modules\Core\Config\ProductionCheck;
use Modules\Core\Frontend\VendorChecksums;
use Modules\Core\Security\Check\ComposerAudit;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * Group `dependencies`: the last `composer audit` (stored by the daily task, `security:audit` or
 * CI; stale after `ProductionCheck::$auditMaxAgeDays`) and the SHA-256 checksums of the vendored
 * front-end libraries (`public/assets/vendor/vendor.json`).
 */
final class DependenciesCheck implements SecurityCheck
{
    private const GROUP = 'dependencies';

    public function __construct(
        private readonly ?ComposerAudit $audit = null,
        private readonly string $vendorAssets = FCPATH . 'assets/vendor',
        private readonly ?ProductionCheck $config = null,
        private readonly ?int $now = null,
    ) {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $this->composerAudit($report);
        $this->vendorChecksums($report);
    }

    private function composerAudit(SecurityReport $report): void
    {
        $config = $this->config ?? config(ProductionCheck::class);
        $last   = ($this->audit ?? new ComposerAudit($config))->last();
        $title  = lang('SecurityCheck.audit.title');

        if ($last === null) {
            $report->warning(self::GROUP, 'dependencies.audit', $title, lang('SecurityCheck.audit.never'), lang('SecurityCheck.audit.fix'));

            return;
        }

        $checkedAt = (string) ($last['checkedAt'] ?? '');
        $time      = strtotime($checkedAt . ' UTC');
        $age       = $time === false ? PHP_INT_MAX : (($this->now ?? time()) - $time);

        if (is_string($last['error'] ?? null) && $last['error'] !== '') {
            $report->warning(self::GROUP, 'dependencies.audit', $title, lang('SecurityCheck.audit.failed', [$checkedAt, $last['error']]), lang('SecurityCheck.audit.fix'));
        } elseif ((int) ($last['advisories'] ?? 0) > 0) {
            $report->error(self::GROUP, 'dependencies.audit', $title, lang('SecurityCheck.audit.advisories', [(int) $last['advisories'], implode(', ', array_map('strval', (array) ($last['packages'] ?? []))), $checkedAt]), lang('SecurityCheck.audit.fixAdvisories'));
        } elseif ($age > $config->auditMaxAgeDays * DAY) {
            $report->warning(self::GROUP, 'dependencies.audit', $title, lang('SecurityCheck.audit.stale', [$checkedAt, $config->auditMaxAgeDays]), lang('SecurityCheck.audit.fix'));
        } else {
            $report->ok(self::GROUP, 'dependencies.audit', $title, lang('SecurityCheck.audit.ok', [$checkedAt]));
        }
    }

    private function vendorChecksums(SecurityReport $report): void
    {
        $title     = lang('SecurityCheck.vendor.title');
        $checksums = new VendorChecksums($this->vendorAssets);

        if (! $checksums->hasManifest()) {
            $report->error(self::GROUP, 'dependencies.vendor', $title, lang('SecurityCheck.vendor.missing'), lang('SecurityCheck.vendor.fix'));

            return;
        }

        $bad = $checksums->problems();

        if ($bad === []) {
            $report->ok(self::GROUP, 'dependencies.vendor', $title, lang('SecurityCheck.vendor.ok', [$checksums->fileCount()]));
        } else {
            $report->error(self::GROUP, 'dependencies.vendor', $title, lang('SecurityCheck.vendor.changed', [implode(', ', $bad)]), lang('SecurityCheck.vendor.fix'));
        }
    }
}
