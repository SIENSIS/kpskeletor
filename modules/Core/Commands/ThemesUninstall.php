<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\Uninstaller;

/**
 * php spark themes:uninstall <slug> [--yes] [--no-backup] [--password]
 *
 * Removes a theme that is not active, not the fallback and not the parent of another theme.
 */
final class ThemesUninstall extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'themes:uninstall';
    protected $description = 'Uninstalls a theme (never the active theme, the fallback or a parent theme).';
    protected $usage       = 'themes:uninstall <slug> [--yes] [--no-backup] [--password]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['slug' => 'The theme (e.g. corporate).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
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
        $slug = (string) ($params[0] ?? '');

        if ($slug === '') {
            CLI::error(lang('Core.packages.cli.noName'));

            return EXIT_ERROR;
        }

        $uninstaller = (new Uninstaller())->onProgress(PackageConsole::say());

        foreach ($problems = $uninstaller->themeProblems($slug) as $problem) {
            CLI::error($problem);
        }

        if ($problems !== []) {
            return EXIT_ERROR;
        }

        $safety = PackageConsole::safety($params);

        if (! $safety['yes'] && CLI::prompt(lang('Core.packages.cli.confirm.uninstallTheme', [$slug]), ['n', 'y']) !== 'y') {
            CLI::write(lang('Core.cancelled'));

            return EXIT_SUCCESS;
        }

        try {
            $uninstaller->uninstallTheme($slug, ['backup' => $safety['backup'], 'password' => $safety['password']]);
        } catch (PackageRefused $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
