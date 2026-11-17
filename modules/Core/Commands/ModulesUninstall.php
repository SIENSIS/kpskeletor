<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\Uninstaller;

/**
 * php spark modules:uninstall <Name> [--keep-data] [--yes] [--no-backup] [--password]
 *
 * Backup, disable, roll back the migrations (unless --keep-data), remove permissions, grants,
 * settings, menu customisations, widgets, tasks and assets, delete the folder (docs/installing-modules.md).
 */
final class ModulesUninstall extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:uninstall';
    protected $description = 'Uninstalls a module: backup, disable, roll back its migrations (unless --keep-data), remove its permissions, grants, settings and folder.';
    protected $usage       = 'modules:uninstall <Name> [--keep-data] [--yes] [--no-backup] [--password]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Name' => 'The module (e.g. News).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--keep-data' => 'Keep its tables and data (the migrations are not rolled back).',
        '--yes'       => 'Do not ask for confirmation.',
        '--no-backup' => 'Go on without a backup when none can be made (not recommended).',
        '--password'  => 'Ask for the backup password (hidden).',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name = (string) ($params[0] ?? '');

        if ($name === '') {
            CLI::error(lang('Core.packages.cli.noName'));

            return EXIT_ERROR;
        }

        $keep        = ModulesSync::option($params, 'keep-data') !== null;
        $uninstaller = (new Uninstaller())->onProgress(PackageConsole::say());

        foreach ($problems = $uninstaller->moduleProblems($name) as $problem) {
            CLI::error($problem);
        }

        if ($problems !== []) {
            return EXIT_ERROR;
        }

        $safety = PackageConsole::safety($params);

        if (! $safety['yes'] && CLI::prompt(lang($keep ? 'Core.packages.cli.confirm.uninstallKeep' : 'Core.packages.cli.confirm.uninstall', [$name]), ['n', 'y']) !== 'y') {
            CLI::write(lang('Core.cancelled'));

            return EXIT_SUCCESS;
        }

        try {
            @set_time_limit(0);
            $uninstaller->uninstallModule($name, ['keepData' => $keep, 'backup' => $safety['backup'], 'password' => $safety['password']]);
        } catch (PackageRefused $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
