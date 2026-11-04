<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Frontend\CspViewScanner;

/**
 * php spark csp:check
 */
final class CspCheck extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'csp:check';
    protected $description = 'Checks the views of app/, modules/ and themes/ for inline scripts, event handlers, style attributes and <style> blocks the strict CSP forbids.';
    protected $usage       = 'csp:check';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $violations = (new CspViewScanner())->scan();

        if ($violations === []) {
            CLI::write(lang('Core.csp.clean'), 'green');

            return EXIT_SUCCESS;
        }

        foreach ($violations as $violation) {
            CLI::error($violation);
        }

        CLI::error(lang('Core.csp.failed', [count($violations)]));

        return EXIT_ERROR;
    }
}
