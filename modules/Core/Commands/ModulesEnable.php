<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\ModuleToggleException;
use Modules\Core\Modules\ModuleToggler;

/**
 * php spark modules:enable <Name> [--with-dependencies]
 */
final class ModulesEnable extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:enable';
    protected $description = 'Enables a disabled module (runs its pending migrations); offers to enable its disabled dependencies.';
    protected $usage       = 'modules:enable <Name> [--with-dependencies]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Name' => 'The module to enable (e.g. News).'];

    /**
     * @var array<string, string>
     */
    protected $options = ['--with-dependencies' => 'Also enable its disabled dependencies without asking.'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name = (string) ($params[0] ?? CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));
        $deps = ModulesSync::option($params, 'with-dependencies') !== null;

        $toggler = new ModuleToggler();

        try {
            $enabled = $toggler->enable($name, $deps);
        } catch (ModuleToggleException $e) {
            if (! $e->missingDependencies) {
                CLI::error($e->getMessage());

                return EXIT_ERROR;
            }

            CLI::write($e->getMessage(), 'yellow');

            if (CLI::prompt(lang('Core.toggle.enableDependencies', [implode(', ', $e->modules)]), ['n', 'y']) !== 'y') {
                CLI::write(lang('Core.cancelled'));

                return EXIT_ERROR;
            }

            try {
                $enabled = $toggler->enable($name, true);
            } catch (ModuleToggleException|\RuntimeException $e) {
                CLI::error($e->getMessage());

                return EXIT_ERROR;
            }
        } catch (\RuntimeException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($enabled === []) {
            CLI::write(lang('Core.toggle.alreadyEnabled', [$name]));

            return EXIT_SUCCESS;
        }

        foreach ($enabled as $module) {
            CLI::write(lang('Core.toggle.enabled', [$module]), 'green');
        }

        return EXIT_SUCCESS;
    }
}
