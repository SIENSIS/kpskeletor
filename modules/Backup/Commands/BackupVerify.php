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
 * php spark backup:verify <file> [--password]
 *
 * Decrypts and authenticates a backup and checks every checksum, without restoring anything.
 */
final class BackupVerify extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:verify';
    protected $description = 'Checks that a backup opens with its password and that every checksum matches.';
    protected $usage       = 'backup:verify <file> [--password]';
    protected $arguments   = ['file' => 'File name in writable/backups (see backup:list)'];
    protected $options     = ['--password' => 'Ask for the password (hidden); otherwise backup.password from .env or BACKUP_PASSWORD'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $file = basename((string) ($params[0] ?? ''));

        try {
            $password = (new ConsolePassword())->get(ModulesSync::option($params, 'password') !== null);
            @set_time_limit(0);
            $manifest = (new BackupService())->verify($file, $password);
        } catch (BackupException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(lang('Backup.msg.verified'), 'green');
        CLI::write(lang('Backup.cli.summary', [
            (string) ($manifest['created_at'] ?? ''), lang('Backup.type.' . ($manifest['type'] ?? 'full')),
            (string) ($manifest['core'] ?? ''), count((array) ($manifest['tables'] ?? [])),
            implode(', ', (array) ($manifest['contents']['files'] ?? [])) ?: '—',
        ]));

        return EXIT_SUCCESS;
    }
}
