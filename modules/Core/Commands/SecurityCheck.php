<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Security\Check\CheckResult;
use Modules\Core\Security\Check\Probe\SiteProbe;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityReport;
use Modules\Core\Security\Check\SecurityStatus;

/**
 * php spark security:check [--probe=<url>] [--json] [--only=<group>]
 *
 * The production checklist (docs/security.md): every check with its status, what it means and
 * how to fix it. Exit code 1 when a check is in error (for deployment pipelines). A full run
 * also refreshes the cached results of the Security status page.
 */
final class SecurityCheck extends BaseCommand
{
    protected $group       = 'Security';
    protected $name        = 'security:check';
    protected $description = 'Checks that this installation is ready for production (exit code 1 on errors).';
    protected $usage       = 'security:check [--probe=<url>] [--json] [--only=<group>]';
    protected $options     = [
        '--probe' => 'Also sends requests to the site itself (headers, hidden paths, uploads, redirects, login throttling, HTTPS). The URL of the site, e.g. https://example.org/',
        '--json'  => 'Prints the results as JSON.',
        '--only'  => 'Runs one group: configuration, secrets, installation, accounts, dependencies, outbound, probe or a module\'s group.',
    ];

    /** Status => [label colour, symbol]. */
    private const STYLES = [
        CheckResult::OK      => ['green', 'ok'],
        CheckResult::INFO    => ['cyan', 'info'],
        CheckResult::WARNING => ['yellow', 'warning'],
        CheckResult::ERROR   => ['red', 'error'],
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $only    = $this->option($params, 'only');
        $probe   = $this->option($params, 'probe');
        $checker = new SecurityChecker();

        if ($only !== null && ! in_array($only, $checker->groups(), true)) {
            CLI::error(lang('SecurityCheck.cli.unknownGroup', [$only, implode(', ', $checker->groups())]));

            return EXIT_USER_INPUT;
        }

        if ($only === 'probe' && $probe === null) {
            CLI::error(lang('SecurityCheck.cli.probeUrl'));

            return EXIT_USER_INPUT;
        }

        $report = $checker->run($only, $probe !== null ? new SiteProbe($probe) : null);

        if ($only === null) {
            $status = new SecurityStatus();
            $status->store(self::withoutProbe($report));

            if ($probe !== null) {
                $status->storeProbe(self::probeOnly($report));
            }
        }

        if (ModulesSync::option($params, 'json') !== null) {
            CLI::write((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            self::print($report);
        }

        return $report->hasErrors() ? EXIT_ERROR : EXIT_SUCCESS;
    }

    /**
     * Prints a report grouped, with explanations and fixes for what is not ok.
     */
    public static function print(SecurityReport $report, bool $details = true): void
    {
        foreach ($report->grouped() as $group => $results) {
            if ($details) {
                CLI::newLine();
                CLI::write(lang('SecurityCheck.group.' . $group) === 'SecurityCheck.group.' . $group ? ucfirst($group) : lang('SecurityCheck.group.' . $group), 'white');
            }

            foreach ($results as $result) {
                [$colour, $label] = self::STYLES[$result->status];

                if (! $details && $result->status !== CheckResult::ERROR && $result->status !== CheckResult::WARNING) {
                    continue;
                }

                CLI::write('  ' . CLI::color(str_pad('[' . $label . ']', 10), $colour) . $result->title . ($result->message !== '' ? ': ' . $result->message : ''));

                if ($result->fix !== '' && $result->status !== CheckResult::OK) {
                    CLI::write('            ' . lang('SecurityCheck.cli.fix') . ' ' . $result->fix, 'light_gray');
                }
            }
        }

        $counts = $report->counts();
        CLI::newLine();
        CLI::write(lang('SecurityCheck.summary', [$counts['ok'], $counts['info'], $counts['warning'], $counts['error']]), $counts['error'] > 0 ? 'red' : ($counts['warning'] > 0 ? 'yellow' : 'green'));
    }

    private static function withoutProbe(SecurityReport $report): SecurityReport
    {
        $copy = new SecurityReport($report->checkedAt);

        foreach ($report->results() as $result) {
            if ($result->group !== 'probe') {
                $copy->add($result);
            }
        }

        return $copy;
    }

    private static function probeOnly(SecurityReport $report): SecurityReport
    {
        $copy = new SecurityReport($report->checkedAt);

        foreach ($report->results() as $result) {
            if ($result->group === 'probe') {
                $copy->add($result);
            }
        }

        return $copy;
    }

    /**
     * @param array<int|string, string|null> $params
     */
    private function option(array $params, string $name): ?string
    {
        $value = ModulesSync::option($params, $name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
