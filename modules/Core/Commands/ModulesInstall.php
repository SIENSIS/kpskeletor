<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageInstaller;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\PackageValidator;
use Modules\Core\Packages\StagedPackages;

/**
 * php spark modules:install <file.zip|url> [--enable] [--force] [--demo] [--yes] [--no-backup] [--password] [--signature=<file>]
 * php spark modules:install --pending [--no-backup] [--password]
 *
 * See docs/packages.md. `--pending` runs the operations approved in the Modules screen (staged
 * installs and uninstalls); run it as a user that may write `modules/` and `themes/` (by hand or
 * from its own cron line).
 */
final class ModulesInstall extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:install';
    protected $description = 'Installs or upgrades a module from a ZIP package (file or https URL), or runs the operations approved in the Modules screen (--pending).';
    protected $usage       = 'modules:install <file.zip|url> [--enable] [--force] [--demo] [--yes] [--no-backup] [--password] [--signature=<file>] | --pending';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['package' => 'A ZIP file or an https URL (allowed hosts only).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--enable'    => 'Enable the module (and its disabled dependencies) once installed.',
        '--force'     => 'Allow a downgrade or reinstalling the same version.',
        '--demo'      => 'Load its demo data once installed (development, or Packages.allowDemoInProduction).',
        '--yes'       => 'Do not ask for confirmation.',
        '--no-backup' => 'Go on without a backup when none can be made (not recommended).',
        '--password'  => 'Ask for the backup password (hidden); otherwise backup.password from .env or BACKUP_PASSWORD.',
        '--signature' => 'The minisign signature file (default: <file>.minisig next to the package).',
        '--pending'   => 'Run the installs and uninstalls approved in the Modules screen.',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        @set_time_limit(0);
        $safety = PackageConsole::safety($params);

        if (ModulesSync::option($params, 'pending') !== null) {
            $result = (new StagedPackages())->process(PackageConsole::say(), ['backup' => $safety['backup'], 'password' => $safety['password']]);
            CLI::write(lang('Core.packages.cli.processed', [$result['done'], $result['failed']]), $result['failed'] === 0 ? 'green' : 'red');

            return $result['failed'] === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        }

        return self::install($params, PackageInfo::MODULE, $safety);
    }

    /**
     * Shared by modules:install and themes:install.
     *
     * @param array<int|string, string|null>                      $params
     * @param array{backup: bool, password: string|null, yes: bool} $safety
     */
    public static function install(array $params, string $type, array $safety): int
    {
        $source = (string) ($params[0] ?? '');

        if ($source === '') {
            CLI::error(lang('Core.packages.cli.noSource'));

            return EXIT_ERROR;
        }

        $download = null;

        try {
            $download  = PackageConsole::source($source, $params);
            $validator = new PackageValidator();
            $info      = $validator->validate($download['file'], $download['signature'], $type, $source);
            PackageConsole::describe($info);

            $installer = (new PackageInstaller($validator))->onProgress(PackageConsole::say());
            $force     = ModulesSync::option($params, 'force') !== null;
            $plan      = $installer->plan($info, $force);

            foreach ($plan['problems'] as $problem) {
                CLI::error($problem);
            }

            if ($plan['problems'] !== []) {
                return EXIT_ERROR;
            }

            $question = lang('Core.packages.cli.confirm.' . $plan['action'], [$info->name, $info->version, $plan['from'] ?? '']);

            if (! $safety['yes'] && CLI::prompt($question, ['n', 'y']) !== 'y') {
                CLI::write(lang('Core.cancelled'));

                return EXIT_SUCCESS;
            }

            $installer->install($download['file'], $info, [
                'enable'   => ModulesSync::option($params, 'enable') !== null,
                'force'    => $force,
                'demo'     => ModulesSync::option($params, 'demo') !== null,
                'backup'   => $safety['backup'],
                'password' => $safety['password'],
            ]);

            return EXIT_SUCCESS;
        } catch (PackageRefused $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        } finally {
            if ($download !== null && $download['temporary']) {
                @unlink($download['file']);
            }
        }
    }
}
