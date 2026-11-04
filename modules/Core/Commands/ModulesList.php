<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\ModuleDiscovery;

/**
 * php spark modules:list
 */
final class ModulesList extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:list';
    protected $description = 'Lists the modules found on disk and in the registry, with their versions and state.';
    protected $usage       = 'modules:list';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $modules  = (new ModuleDiscovery())->discover();
        $registry = db_connect()->tableExists('modules') ? model(ModuleModel::class, false)->allByName() : [];
        $rows     = [];

        foreach ($modules as $name => $module) {
            $installed = $registry[$name] ?? null;
            $version   = $module->manifest->version ?? '?';

            $status = match (true) {
                ! $module->isValid()                  => lang('Core.list.statusInvalid'),
                $installed === null                   => lang('Core.list.statusNew'),
                $installed['version'] !== $version    => lang('Core.list.statusUpgrade'),
                default                               => lang('Core.list.statusOk'),
            };

            $rows[] = [
                $name,
                $version,
                $installed['version'] ?? '-',
                $installed === null ? '-' : lang($installed['enabled'] ? 'Core.list.yes' : 'Core.list.no'),
                $status,
            ];
        }

        foreach (array_diff_key($registry, $modules) as $name => $installed) {
            $rows[] = [$name, '-', $installed['version'], lang($installed['enabled'] ? 'Core.list.yes' : 'Core.list.no'), lang('Core.list.statusMissing')];
        }

        if ($rows === []) {
            CLI::write(lang('Core.list.empty'));

            return EXIT_SUCCESS;
        }

        CLI::table($rows, [
            lang('Core.list.module'),
            lang('Core.list.version'),
            lang('Core.list.installed'),
            lang('Core.list.enabled'),
            lang('Core.list.status'),
        ]);

        return EXIT_SUCCESS;
    }
}
