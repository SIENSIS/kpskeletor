<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\NamespaceFile;

/**
 * php spark make:module <Name>
 */
final class MakeModule extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'make:module';
    protected $description = 'Generates an empty module in modules/<Name> and registers its namespace.';
    protected $usage       = 'make:module <Name>';

    /** @var array<string, string> */
    protected $arguments = [
        'Name' => 'Module name in StudlyCase (e.g. News)',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name = trim((string) ($params[0] ?? ''));

        if ($name === '') {
            $name = trim(CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));
        }

        $generator = new ModuleGenerator();
        $error     = $generator->nameError($name);

        if ($error !== null) {
            CLI::error($error);

            return EXIT_ERROR;
        }

        $path = $generator->generate($name);
        (new NamespaceFile())->write((new ModuleDiscovery())->folders());

        CLI::write(lang('Core.make.created', [$name, clean_path($path)]), 'green');
        CLI::write(lang('Core.make.next'));

        return EXIT_SUCCESS;
    }
}
