<?php

declare(strict_types=1);

namespace Modules\Backup\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\ConsolePassword;
use Modules\Backup\Services\EnvFile;
use Modules\Backup\Services\RestoreService;
use Modules\Core\Commands\ModulesSync;

/**
 * php spark backup:restore <file> [--only=db|files] [--with-env] [--password] [--yes]
 *
 * Restores a backup (RestoreService: checks, safety backup, maintenance mode, database and/or
 * files, `modules:sync`). The current `.env` is kept; when the backup's secrets
 * (`encryption.key`, `app.maskKey`, `backup.password`) differ they are merged after a
 * confirmation; `--with-env` replaces the whole `.env` with the backup's (console only).
 * `--yes` answers the confirmations (scripts); without it the file name must be typed.
 */
final class BackupRestore extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:restore';
    protected $description = 'Restores a backup (safety backup first, maintenance mode, modules:sync).';
    protected $usage       = 'backup:restore <file> [--only=db|files] [--with-env] [--password] [--yes]';
    protected $arguments   = ['file' => 'File name in writable/backups (see backup:list)'];
    protected $options     = [
        '--only'     => 'Only the database (db) or only the files (files)',
        '--with-env' => 'Replace the whole .env with the backup\'s (otherwise only its secrets are merged)',
        '--password' => 'Ask for the password (hidden); otherwise backup.password from .env or BACKUP_PASSWORD',
        '--yes'      => 'Do not ask for confirmations',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $file    = basename((string) ($params[0] ?? ''));
        $only    = ModulesSync::option($params, 'only');
        $withEnv = ModulesSync::option($params, 'with-env') !== null;
        $yes     = ModulesSync::option($params, 'yes') !== null;

        if ($only !== null && ! in_array($only, ['db', 'files'], true)) {
            CLI::error(lang('Backup.error.type'));

            return EXIT_ERROR;
        }

        if (! $yes && CLI::prompt(lang('Backup.cli.confirmRestore', [$file])) !== $file) {
            CLI::write(lang('Backup.cli.cancelled'), 'yellow');

            return EXIT_ERROR;
        }

        $env = new EnvFile(config(BackupConfig::class)->envPath);

        try {
            $password = (new ConsolePassword())->get(ModulesSync::option($params, 'password') !== null);
            @set_time_limit(0);
            $result = (new RestoreService(env: $env))->restore($file, $password, is_string($only) ? $only : null, null, 'console', static function (string $step, int $percent): void {
                CLI::write(sprintf('[%3d%%] %s', $percent, lang('Backup.step.' . $step)));
            });
        } catch (BackupException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(lang('Backup.msg.restored'), 'green');

        if ($result->safetyFile !== null) {
            CLI::write(lang('Backup.msg.safety', [$result->safetyFile]));
        }

        if ($withEnv && $result->backupEnv !== null) {
            if ($yes || CLI::prompt(lang('Backup.cli.confirmWithEnv'), ['n', 'y']) === 'y') {
                $env->write($result->backupEnv);
                CLI::write(lang('Backup.cli.envReplaced'), 'green');
            }
        } elseif ($result->envDifferences !== [] && $result->mergedEnv !== null) {
            CLI::write(lang('Backup.msg.secretsDiffer', [implode(', ', $result->envDifferences)]), 'yellow');

            if ($yes || CLI::prompt(lang('Backup.cli.confirmMerge'), ['n', 'y']) === 'y') {
                $env->write($result->mergedEnv);
                CLI::write(lang('Backup.cli.envMerged'), 'green');
            } else {
                CLI::write(lang('Backup.cli.envKept'), 'yellow');
            }
        }

        return EXIT_SUCCESS;
    }
}
