<?php

declare(strict_types=1);

namespace Modules\Updates\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupPassword;
use Modules\Backup\Services\ConsolePassword;
use Modules\Core\Commands\ModulesSync;
use Modules\Core\Config\Version;
use Modules\Updates\Services\UpdateException;
use Modules\Updates\Services\Updater;

/**
 * php spark app:update [--dry-run] [--yes] [--password]
 *
 * Updates the application to the newest release of `Config\Updates::$repository` (Updater):
 * checks, verified download (SHA-256 + Ed25519 signature, or `git verify-tag`), maintenance
 * mode, a full backup, the new code, `composer install --no-dev`, `modules:sync`, caches.
 * `--dry-run` only shows the plan and what would stop it. The backup password comes from
 * `backup.password` (`.env`) or `BACKUP_PASSWORD`, or is asked for with `--password`.
 */
final class AppUpdate extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'app:update';
    protected $description = 'Updates the application to the newest verified release (maintenance mode and a backup first).';
    protected $usage       = 'app:update [--dry-run] [--yes] [--password]';
    protected $options     = [
        '--dry-run'  => 'Show the plan and the checks without changing anything',
        '--yes'      => 'Do not ask for confirmation',
        '--password' => 'Ask for the backup password (hidden); otherwise backup.password from .env or BACKUP_PASSWORD',
    ];

    /** Replaced by tests. */
    public static ?Updater $updater = null;

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $updater = self::$updater ?? new Updater(output: static function (string $line, ?string $color): void {
            CLI::write($line, $color);
        });
        $dryRun = ModulesSync::option($params, 'dry-run') !== null;

        try {
            $release = $updater->available();
        } catch (UpdateException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($release === null) {
            CLI::write(lang('Updates.cli.upToDate', [Version::CORE]), 'green');

            return EXIT_SUCCESS;
        }

        CLI::write(lang('Updates.cli.available', [Version::CORE, $release->version, $updater->method()]), 'yellow');
        CLI::write(lang('Updates.cli.plan'));

        foreach ($updater->plan($release) as $i => $step) {
            CLI::write('  ' . ($i + 1) . '. ' . $step);
        }

        try {
            $problems = $updater->problems($release);
        } catch (UpdateException $e) {
            $problems = [$e->getMessage()];
        }

        if (ModulesSync::option($params, 'password') === null && (new BackupPassword())->configured() === null) {
            $problems[] = lang('Updates.error.noBackupPassword');
        }

        if ($updater->method() === 'archive' && $updater->installedFiles() === null) {
            CLI::write(lang('Updates.cli.noFileList'), 'yellow');
        }

        foreach ($problems as $problem) {
            CLI::error($problem);
        }

        if ($dryRun) {
            CLI::write($problems === [] ? lang('Updates.cli.dryRunOk') : lang('Updates.cli.dryRunBlocked'), $problems === [] ? 'green' : 'red');

            return $problems === [] ? EXIT_SUCCESS : EXIT_ERROR;
        }

        if ($problems !== []) {
            return EXIT_ERROR;
        }

        if (ModulesSync::option($params, 'yes') === null && CLI::prompt(lang('Updates.cli.confirm', [$release->version]), ['n', 'y']) !== 'y') {
            CLI::write(lang('Updates.cli.cancelled'));

            return EXIT_SUCCESS;
        }

        try {
            $password = (new ConsolePassword())->get(ModulesSync::option($params, 'password') !== null, true);
            @set_time_limit(0);
            $updater->update($release, $password);
        } catch (UpdateException|BackupException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
