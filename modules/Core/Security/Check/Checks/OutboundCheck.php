<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use Config\Outbound;
use Modules\Core\Http\OutboundAllowList;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Throwable;

/**
 * Group `outbound` (information): the hosts the OutboundClient may call, and the hosts each
 * enabled module added in its manifest (`$outboundHosts`).
 */
final class OutboundCheck implements SecurityCheck
{
    private const GROUP = 'outbound';

    /**
     * @param list<string>|null                $hosts       the whole allow-list (tests)
     * @param array<string, list<string>>|null $moduleHosts module => hosts (tests)
     */
    public function __construct(private readonly ?array $hosts = null, private readonly ?array $moduleHosts = null)
    {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $hosts   = $this->hosts ?? (new OutboundAllowList(config(Outbound::class)))->hosts();
        $modules = $this->moduleHosts ?? self::byModule();

        $report->info(self::GROUP, 'outbound.allowList', lang('SecurityCheck.outbound.title'), $hosts === [] ? lang('SecurityCheck.outbound.none') : lang('SecurityCheck.outbound.hosts', [implode(', ', $hosts)]), lang('SecurityCheck.outbound.fix'));

        if ($modules !== []) {
            $lines = [];

            foreach ($modules as $module => $list) {
                $lines[] = $module . ': ' . implode(', ', $list);
            }

            $report->info(self::GROUP, 'outbound.modules', lang('SecurityCheck.outbound.modulesTitle'), implode('; ', $lines));
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private static function byModule(): array
    {
        try {
            $declarations = (new DeclarationRegistry())->all('outbound');
        } catch (Throwable) {
            return [];
        }

        $modules = [];

        foreach ($declarations as $key => $declaration) {
            $modules[$declaration['module']][] = substr((string) $key, strpos((string) $key, '.') + 1);
        }

        ksort($modules);

        return $modules;
    }
}
