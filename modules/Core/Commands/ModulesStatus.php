<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleMigrator;

/**
 * php spark modules:status
 */
final class ModulesStatus extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:status';
    protected $description = 'Shows the pending migrations of the packages and of every module.';
    protected $usage       = 'modules:status';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $migrator   = new ModuleMigrator();
        $namespaces = ModuleMigrator::PACKAGE_NAMESPACES;

        foreach ((new ModuleDiscovery())->discover() as $module) {
            $namespaces[] = $module->namespace;
        }

        $rows = [];

        foreach ($namespaces as $namespace) {
            foreach ($migrator->pending($namespace) as $migration) {
                $rows[] = [$namespace, $migration];
            }
        }

        if ($rows === []) {
            CLI::write(lang('Core.status.upToDate'), 'green');

            return EXIT_SUCCESS;
        }

        CLI::table($rows, [lang('Core.status.namespace'), lang('Core.status.pending')]);

        return EXIT_SUCCESS;
    }
}
