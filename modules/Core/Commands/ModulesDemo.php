<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Demo\DemoDataException;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Demo\DemoRemoval;

/**
 * php spark modules:demo <Name> [--remove|--reload]
 */
final class ModulesDemo extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:demo';
    protected $description = 'Loads, removes or reloads the demo data of a module (asks for confirmation; refused in production unless Packages.allowDemoInProduction).';
    protected $usage       = 'modules:demo <Name> [--remove|--reload]';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Name' => 'The module (e.g. News).'];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--remove' => 'Remove the demo data (only the recorded rows and files).',
        '--reload' => 'Remove the demo data and load it again.',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name    = (string) ($params[0] ?? CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));
        $action  = ModulesSync::option($params, 'reload') !== null ? 'reload' : (ModulesSync::option($params, 'remove') !== null ? 'remove' : 'load');
        $manager = new DemoDataManager();

        try {
            $manager->assertAllowed();

            if (CLI::prompt(lang('Core.demo.confirm.' . $action, [$name]), ['n', 'y']) !== 'y') {
                CLI::write(lang('Core.cancelled'));

                return EXIT_ERROR;
            }

            if ($action === 'load') {
                CLI::write(lang($manager->load($name) ? 'Core.demo.loaded' : 'Core.demo.alreadyLoaded', [$name]), 'green');

                return EXIT_SUCCESS;
            }

            $removal = $action === 'reload' ? $manager->reload($name) : $manager->remove($name);
        } catch (DemoDataException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        $this->report($name, $removal);

        if ($action === 'reload' && $removal->kept === []) {
            CLI::write(lang('Core.demo.loaded', [$name]), 'green');
        }

        return $removal->kept === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function report(string $name, DemoRemoval $removal): void
    {
        CLI::write(lang('Core.demo.removed', [$name, $removal->rows, $removal->files]), 'green');

        if ($removal->kept !== []) {
            CLI::write(lang('Core.demo.kept', [implode(', ', $removal->kept)]), 'yellow');
        }
    }
}
