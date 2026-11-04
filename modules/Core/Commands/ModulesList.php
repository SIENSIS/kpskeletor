<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Demo\DemoDataManager;
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
        $demo     = db_connect()->tableExists('demo_records') ? (new DemoDataManager())->loadedModules() : [];
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
                in_array($name, $demo, true) ? lang('Core.list.yes') : '-',
            ];
        }

        foreach (array_diff_key($registry, $modules) as $name => $installed) {
            $rows[] = [$name, '-', $installed['version'], lang($installed['enabled'] ? 'Core.list.yes' : 'Core.list.no'), lang('Core.list.statusMissing'), in_array($name, $demo, true) ? lang('Core.list.yes') : '-'];
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
            lang('Core.list.demo'),
        ]);

        return EXIT_SUCCESS;
    }
}
