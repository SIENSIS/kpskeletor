<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\ModuleToggleException;
use Modules\Core\Modules\ModuleToggler;

/**
 * php spark modules:disable <Name>
 */
final class ModulesDisable extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:disable';
    protected $description = 'Disables a module: its routes answer 404 and it disappears from menus, registries and permissions; no data is deleted.';
    protected $usage       = 'modules:disable <Name>';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Name' => 'The module to disable (e.g. News).'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name = (string) ($params[0] ?? CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));

        try {
            $changed = (new ModuleToggler())->disable($name);
        } catch (ModuleToggleException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(lang($changed ? 'Core.toggle.disabled' : 'Core.toggle.alreadyDisabled', [$name]), $changed ? 'green' : null);

        return EXIT_SUCCESS;
    }
}
