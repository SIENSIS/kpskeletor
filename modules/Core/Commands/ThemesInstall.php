<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use Modules\Core\Packages\PackageInfo;

/**
 * php spark themes:install <file.zip|url> [--force] [--yes] [--no-backup] [--password] [--signature=<file>]
 *
 * Installs or upgrades a theme from a ZIP package with the same validation as modules
 * (docs/packages.md). Choosing the active theme is a setting (Modules › Themes, Settings › Theme).
 */
final class ThemesInstall extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'themes:install';
    protected $description = 'Installs or upgrades a theme from a ZIP package (file or https URL).';
    protected $usage       = 'themes:install <file.zip|url> [--force] [--yes] [--no-backup] [--password] [--signature=<file>]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['package' => 'A ZIP file or an https URL (allowed hosts only).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--force'     => 'Allow a downgrade or reinstalling the same version.',
        '--yes'       => 'Do not ask for confirmation.',
        '--no-backup' => 'Go on without a backup when none can be made (not recommended).',
        '--password'  => 'Ask for the backup password (hidden).',
        '--signature' => 'The minisign signature file (default: <file>.minisig next to the package).',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        @set_time_limit(0);

        return ModulesInstall::install($params, PackageInfo::THEME, PackageConsole::safety($params));
    }
}
