<?php

declare(strict_types=1);

namespace Modules\Backup\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Backup\Services\Retention;

/**
 * php spark backup:prune
 *
 * Applies the retention settings now (`Backup.keepDaily`, `keepWeekly`, `keepMonthly`).
 */
final class BackupPrune extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:prune';
    protected $description = 'Deletes the backups the retention settings do not keep.';
    protected $usage       = 'backup:prune';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $deleted = (new Retention())->prune();

        foreach ($deleted as $file) {
            CLI::write('  - ' . $file);
        }

        CLI::write(lang('Backup.msg.pruned', [count($deleted)]), 'green');

        return EXIT_SUCCESS;
    }
}
