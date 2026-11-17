<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use CodeIgniter\Events\Events;
use Modules\Core\Security\Check\Checks\ConfigurationCheck;
use Modules\Core\Security\Check\Checks\DependenciesCheck;
use Modules\Core\Security\Check\Checks\InstallationCheck;
use Modules\Core\Security\Check\Checks\OutboundCheck;
use Modules\Core\Security\Check\Checks\SecretsCheck;
use Modules\Core\Security\Check\Probe\SiteProbe;
use Throwable;

/**
 * Runs the checks of `security:check` and of the Security status page (docs/security.md,
 * "Production checklist"): the Core's own groups and those modules add from the
 * `securityChecks` event, optionally limited to one group, plus the external probe when asked.
 * A check that throws is reported as an error (never a crash, never a silent pass).
 */
final class SecurityChecker
{
    /**
     * @param list<SecurityCheck>|null $checks Default: the Core's checks and the event's
     */
    public function __construct(private readonly ?array $checks = null)
    {
    }

    public function run(?string $only = null, ?SiteProbe $probe = null): SecurityReport
    {
        $report = new SecurityReport(gmdate('Y-m-d H:i:s'));

        foreach ($this->checks ?? self::defaultChecks() as $check) {
            if ($only !== null && ! in_array($only, $check->groups(), true)) {
                continue;
            }

            $this->runOne($check, $report);
        }

        if ($probe !== null && ($only === null || $only === 'probe')) {
            $this->runOne($probe, $report);
        }

        if ($only !== null) {
            // A check may report into a group it does not list first: keep only the asked one.
            $filtered = new SecurityReport($report->checkedAt);

            foreach ($report->results() as $result) {
                if ($result->group === $only) {
                    $filtered->add($result);
                }
            }

            return $filtered;
        }

        return $report;
    }

    /**
     * Every known group (Core and modules), for `--only` and the page.
     *
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = [...SecurityReport::GROUPS];

        foreach ($this->checks ?? self::defaultChecks() as $check) {
            $groups = [...$groups, ...$check->groups()];
        }

        return array_values(array_unique($groups));
    }

    /**
     * @param string $environment the environment the configuration is checked for (app:install
     *                            checks the one it installed)
     *
     * @return list<SecurityCheck>
     */
    public static function defaultChecks(string $environment = ENVIRONMENT): array
    {
        $checks = new SecurityChecks();
        $checks->add(new ConfigurationCheck($environment));
        $checks->add(new SecretsCheck());
        $checks->add(new InstallationCheck());
        $checks->add(new DependenciesCheck());
        $checks->add(new OutboundCheck());

        try {
            Events::trigger(SecurityChecks::EVENT, $checks);
        } catch (Throwable) {
            // a listener that cannot run (not installed yet) adds nothing
        }

        return $checks->all();
    }

    private function runOne(SecurityCheck $check, SecurityReport $report): void
    {
        try {
            $check->run($report);
        } catch (Throwable $e) {
            $report->error($check->groups()[0] ?? 'configuration', 'check.' . strtolower((string) preg_replace('/\W+/', '', substr((string) strrchr('\\' . $check::class, '\\'), 1))), lang('SecurityCheck.failed.title', [substr((string) strrchr('\\' . $check::class, '\\'), 1)]), $e->getMessage(), lang('SecurityCheck.failed.fix'));
        }
    }
}
