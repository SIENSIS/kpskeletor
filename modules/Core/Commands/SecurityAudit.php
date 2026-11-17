<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Security\Check\ComposerAudit;

/**
 * php spark security:audit [--from=<file>]
 *
 * Runs `composer audit` and stores its result for `security:check` ("last checked on …"), or
 * stores the JSON output of a `composer audit --format=json` run elsewhere (CI) with --from.
 * Exit code 1 when there are advisories or the audit could not run.
 */
final class SecurityAudit extends BaseCommand
{
    protected $group       = 'Security';
    protected $name        = 'security:audit';
    protected $description = 'Runs composer audit and stores the result for security:check.';
    protected $usage       = 'security:audit [--from=<file>]';
    protected $options     = [
        '--from' => 'Stores the output of `composer audit --format=json` from this file instead of running Composer.',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $audit = new ComposerAudit();
        $from  = ModulesSync::option($params, 'from');

        if (is_string($from) && $from !== '') {
            if (! is_file($from) || ! is_readable($from)) {
                CLI::error(lang('SecurityCheck.cli.noFile', [$from]));

                return EXIT_USER_INPUT;
            }

            $status = $audit->store((string) file_get_contents($from));
        } else {
            $status = $audit->run();
        }

        if (is_string($status['error'] ?? null)) {
            CLI::error(lang('SecurityCheck.audit.failed', [(string) $status['checkedAt'], $status['error']]));

            return EXIT_ERROR;
        }

        if ((int) $status['advisories'] > 0) {
            CLI::error(lang('SecurityCheck.audit.advisories', [(int) $status['advisories'], implode(', ', (array) $status['packages']), (string) $status['checkedAt']]));

            return EXIT_ERROR;
        }

        CLI::write(lang('SecurityCheck.audit.ok', [(string) $status['checkedAt']]), 'green');

        return EXIT_SUCCESS;
    }
}
