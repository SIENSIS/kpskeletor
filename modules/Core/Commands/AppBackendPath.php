<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Routing\BackendPath;
use Modules\Core\Routing\BackendPathChangeFailed;
use Modules\Core\Routing\BackendPathChanger;

/**
 * php spark app:backend-path [<new>] [--yes]
 *
 * Changes the back-office prefix chosen at install time (D-40-01): validates the new path like
 * the installer, turns maintenance mode on, rewrites `app.backendPath` in `.env`, rewrites the
 * stored links that still carry the old prefix (event `backendPathChanged`), clears the caches,
 * writes a security log entry and turns maintenance off. Without an argument it prints the
 * current prefix. Never available from the back office.
 */
final class AppBackendPath extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'app:backend-path';
    protected $description = 'Shows or changes the back-office path prefix (app.backendPath).';
    protected $usage       = 'app:backend-path [<new>] [--yes]';
    protected $arguments   = ['new' => 'The new first path segment of the back office (e.g. admin, intranet)'];
    protected $options     = ['--yes' => 'Do not ask for confirmation'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $old = BackendPath::current();
        $new = strtolower(trim((string) ($params[0] ?? '')));

        if ($new === '') {
            CLI::write(lang('Core.backendPath.cliCurrent', [$old]));

            return EXIT_SUCCESS;
        }

        $changer = new BackendPathChanger();
        $error   = $changer->error($new);

        if ($error !== null) {
            CLI::error($error);

            return EXIT_ERROR;
        }

        if (! $changer->envWritable()) {
            CLI::error(lang('Core.backendPath.cliEnvNotWritable'));
            CLI::write(BackendPathChanger::envLine($new));
            CLI::write(lang('Core.backendPath.cliThenRun'));

            return EXIT_ERROR;
        }

        CLI::write(lang('Core.backendPath.cliWarning', [$old, $new]), 'yellow');

        if (ModulesSync::option($params, 'yes') === null && CLI::prompt(lang('Core.backendPath.cliConfirm'), ['n', 'y']) !== 'y') {
            CLI::write(lang('Core.backendPath.cliCancelled'));

            return EXIT_ERROR;
        }

        try {
            $rows = $changer->change($new);
        } catch (BackendPathChangeFailed $e) {
            CLI::error(lang('Core.backendPath.cliFailed', [$e->step, $e->getMessage()]));
            CLI::write(lang('Core.backendPath.cliUndo', [BackendPathChanger::envLine($e->old)]), 'yellow');

            return EXIT_ERROR;
        }

        foreach ($rows as $what => $count) {
            CLI::write('  ' . $what . ': ' . $count);
        }

        CLI::write(lang('Core.backendPath.cliDone', [$new]), 'green');
        CLI::write(lang('Core.backendPath.cliAfter'));

        return EXIT_SUCCESS;
    }
}
