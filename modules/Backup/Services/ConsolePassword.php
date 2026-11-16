<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Closure;
use CodeIgniter\CLI\CLI;

/**
 * The password of a backup command (D-00-47): `--password` takes no value and asks with a
 * hidden prompt (never on the command line: shell history, process list); without it the
 * password comes from `backup.password` in `.env` or the `BACKUP_PASSWORD` variable.
 */
final class ConsolePassword
{
    /** @var Closure(string): string */
    private Closure $reader;

    /**
     * @param (Closure(string): string)|null $reader Reads a hidden line after a prompt (tests)
     */
    public function __construct(?Closure $reader = null, private readonly BackupPassword $passwords = new BackupPassword())
    {
        $this->reader = $reader ?? self::hiddenPrompt(...);
    }

    /**
     * @param bool $ask    `--password` was given
     * @param bool $twice  ask again to confirm (new backups)
     *
     * @throws BackupException
     */
    public function get(bool $ask, bool $twice = false): string
    {
        if (! $ask) {
            $password = $this->passwords->configured();

            if ($password === null) {
                throw new BackupException(lang('Backup.cli.noPassword'));
            }

            return $password;
        }

        $password = ($this->reader)(lang('Backup.cli.password'));

        if ($twice && ! hash_equals($password, ($this->reader)(lang('Backup.cli.passwordAgain')))) {
            throw new BackupException(lang('Backup.error.passwordMismatch'));
        }

        return $password;
    }

    private static function hiddenPrompt(string $prompt): string
    {
        CLI::print($prompt . ' ');
        $tty = defined('STDIN') && stream_isatty(STDIN);

        if ($tty) {
            shell_exec('stty -echo 2>/dev/null');
        }

        $line = defined('STDIN') ? fgets(STDIN) : false;

        if ($tty) {
            shell_exec('stty echo 2>/dev/null');
        }

        CLI::newLine();

        return rtrim((string) $line, "\r\n");
    }
}
