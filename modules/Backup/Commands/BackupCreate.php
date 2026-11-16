<?php

declare(strict_types=1);

namespace Modules\Backup\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupService;
use Modules\Backup\Services\ConsolePassword;
use Modules\Core\Commands\ModulesSync;

/**
 * php spark backup:create [--only=db|files] [--password]
 *
 * Creates an encrypted backup in `writable/backups` (docs/backup-restore.md). `--password` asks
 * for the password (twice, hidden); without it `backup.password` (`.env`) or `BACKUP_PASSWORD`.
 */
final class BackupCreate extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:create';
    protected $description = 'Creates an encrypted backup (database, files, .env) in writable/backups.';
    protected $usage       = 'backup:create [--only=db|files] [--password]';
    protected $options     = [
        '--only'     => 'Only the database (db) or only the files (files)',
        '--password' => 'Ask for the password (hidden); otherwise backup.password from .env or BACKUP_PASSWORD',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $only = ModulesSync::option($params, 'only');
        $type = $only === null ? 'full' : $only;

        if (! in_array($type, ['full', 'db', 'files'], true)) {
            CLI::error(lang('Backup.error.type'));

            return EXIT_ERROR;
        }

        try {
            $password = (new ConsolePassword())->get(ModulesSync::option($params, 'password') !== null, true);
            CLI::write(lang('Backup.cli.creating'));
            @set_time_limit(0);
            $row = (new BackupService())->create($password, $type, 'console');
        } catch (BackupException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(lang('Backup.msg.created', [(string) $row['file'], ui_bytes((int) $row['size'])]), 'green');

        return EXIT_SUCCESS;
    }
}
