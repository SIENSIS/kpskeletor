<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\SyncFailedException;

/**
 * php spark modules:sync
 */
final class ModulesSync extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:sync';
    protected $description = 'Discovers the modules, validates their manifests, runs migrations and seeders, and updates the registry. Idempotent.';
    protected $usage       = 'modules:sync';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        try {
            $report = (new ModuleSynchronizer())->sync();
        } catch (SyncFailedException $e) {
            CLI::error(lang('Core.sync.failed'));

            foreach ($e->errors as $error) {
                CLI::error('  - ' . $error);
            }

            return EXIT_ERROR;
        }

        foreach ($report->lines() as $line) {
            CLI::write($line);
        }

        CLI::write($report->hasChanges() ? lang('Core.sync.done') : lang('Core.sync.nothing'), 'green');

        return EXIT_SUCCESS;
    }
}
