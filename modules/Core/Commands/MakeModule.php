<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Modules\CrudScaffold;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\NamespaceFile;

/**
 * php spark make:module <Name> [--crud=<table>]
 *
 * With `--crud`, the migration creates that table, the manifest declares its `crud` resource
 * (`<module>-<key>`), the table is migrated and `php spark kpcrudy:make` writes the controller and
 * the route, which are then rewritten to `CrudFactory` (`CrudScaffold`, D-00-09). kpcrudy:make
 * runs as its own process: it reads its options from the command line.
 */
final class MakeModule extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'make:module';
    protected $description = 'Generates a module in modules/<Name> (optionally with a kpcrudy CRUD) and registers its namespace.';
    protected $usage       = 'make:module <Name> [--crud=<table>]';

    /** @var array<string, string> */
    protected $arguments = [
        'Name' => 'Module name in StudlyCase (e.g. News)',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--crud' => 'Table of a kpcrudy CRUD to generate (e.g. news_posts): migration, resource, controller and route',
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

        $crud  = ModulesSync::option($params, 'crud');
        $table = is_string($crud) ? trim($crud) : null;

        $generator = new ModuleGenerator();
        $error     = $generator->nameError($name) ?? ($table !== null ? CrudScaffold::tableError($table) : null);

        if ($crud === true || $error !== null) {
            CLI::error($error ?? lang('Core.make.invalidTable', ['']));

            return EXIT_ERROR;
        }

        $path = $generator->generate($name, $table);
        (new NamespaceFile())->write((new ModuleDiscovery())->folders());

        CLI::write(lang('Core.make.created', [$name, clean_path($path)]), 'green');

        if ($table !== null) {
            return $this->crud($name, $table, $path);
        }

        CLI::write(lang('Core.make.next'));

        return EXIT_SUCCESS;
    }

    /**
     * Migrates the module's table, runs kpcrudy:make and rewrites its output.
     */
    private function crud(string $name, string $table, string $path): int
    {
        $namespace = 'Modules\\' . $name;
        $resource  = CrudScaffold::resource($name, $table);
        $arguments = CrudScaffold::makeArguments($name, $table);

        CLI::write(lang('Core.make.migrating', [$table, $namespace]));

        if (($error = $this->spark(['migrate', '-n', $namespace])) !== null) {
            CLI::error(lang('Core.make.crudFailed', [$error]));

            return EXIT_ERROR;
        }

        CLI::write(lang('Core.make.crudGenerating', [implode(' ', array_map(escapeshellarg(...), array_slice($arguments, 1)))]));

        if (($error = $this->spark($arguments)) !== null) {
            CLI::error(lang('Core.make.crudFailed', [$error]));

            return EXIT_ERROR;
        }

        $controller = $path . '/Controllers/' . ucfirst(CrudScaffold::resourceKey($name, $table)) . '.php';
        $routes     = $path . '/Config/Routes.php';

        file_put_contents($controller, CrudScaffold::rewriteController((string) file_get_contents($controller), $resource));
        file_put_contents($routes, CrudScaffold::rewriteRoutes((string) file_get_contents($routes), $resource));

        CLI::write(lang('Core.make.crudCreated', [$resource, clean_path($controller), strtolower($name) . '/' . CrudScaffold::resourceKey($name, $table)]), 'green');
        CLI::write(lang('Core.make.crudNext', [clean_path($controller)]));

        return EXIT_SUCCESS;
    }

    /**
     * Runs `php spark …` in a new process (so it sees the new module's namespace and parses its
     * own options); null on success, otherwise its output.
     *
     * @param list<string> $arguments
     */
    private function spark(array $arguments): ?string
    {
        $command = implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, ROOTPATH . 'spark', ...$arguments, '--no-header']));
        $output  = [];
        exec($command . ' 2>&1', $output, $status);

        return $status === 0 ? null : trim(implode("\n", $output));
    }
}
