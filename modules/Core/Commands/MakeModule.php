<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Engine;
use Modules\Core\Generator\GeneratorException;
use Modules\Core\Generator\InvalidDefinition;
use Modules\Core\Generator\PlanWriter;
use Modules\Core\Modules\ApiScaffold;
use Modules\Core\Modules\CrudScaffold;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\NamespaceFile;

/**
 * php spark make:module <Name> [--crud=<table>] [--api] [--standalone]
 * php spark make:module --definition=<file.json> [--dry-run]
 *
 * With `--definition` the module (new, or an existing one with `"existing": true`) is built by the
 * code generator (`Modules\Core\Generator\Engine`, the engine of Devtools › Code generator) from
 * a JSON definition (docs/devtools.md): tables, migrations, demo data, CRUDs, API, menus,
 * permissions, texts and tests; then `modules:sync` runs. `--dry-run` only lists the plan.
 *
 * Every module gets its own `CLAUDE.md` (purpose, resources and permissions, decisions, open tasks,
 * how to test). With `--standalone` (a module developed in its own repository, inside a checkout
 * of the skeleton) it also gets a `composer.json`, a `README.md`, and its `CLAUDE.md` summarises
 * the skeleton's rules.
 *
 * With `--crud`, the migration creates that table, the manifest declares its `crud` resource
 * (`<module>-<key>`), the table is migrated and `php spark kpcrudy:make` writes the controller and
 * the route, which are then rewritten to `CrudFactory` (`CrudScaffold`, D-00-09). kpcrudy:make
 * runs as its own process: it reads its options from the command line.
 *
 * With `--api` (needs `--crud`, D-19-03), `php spark kpapy:make` writes the API resource of the same
 * table (`api/v1/<module>/<key>`, `setResource()` = the CRUD's resource, `kpapy` filter), the
 * manifest declares it in `$api` and `Tests/<Name>ApiTest.php` checks the token and its scopes
 * (`ApiScaffold`).
 */
final class MakeModule extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'make:module';
    protected $description = 'Generates a module in modules/<Name> (optionally with a kpcrudy CRUD) and registers its namespace.';
    protected $usage       = 'make:module <Name> [--crud=<table>] [--api] [--standalone] | make:module --definition=<file.json> [--dry-run]';

    /** @var array<string, string> */
    protected $arguments = [
        'Name' => 'Module name in StudlyCase (e.g. News)',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--crud'       => 'Table of a kpcrudy CRUD to generate (e.g. news_posts): migration, resource, controller and route',
        '--api'        => 'With --crud: also the kpapy API resource of the table (api/v1/<module>/<key>), its $api entry and a scope test',
        '--standalone' => 'For a module kept in its own repository: also composer.json, README.md and a CLAUDE.md with the skeleton\'s rules',
        '--definition' => 'A JSON definition of the code generator (docs/devtools.md): builds a new module or adds tables to an existing one',
        '--dry-run'    => 'With --definition: list the files of the plan without writing them',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $definition = ModulesSync::option($params, 'definition');

        if ($definition !== null) {
            return $this->fromDefinition(is_string($definition) ? $definition : '', ModulesSync::option($params, 'dry-run') !== null);
        }

        $name = trim((string) ($params[0] ?? ''));

        if ($name === '') {
            $name = trim(CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));
        }

        $crud  = ModulesSync::option($params, 'crud');
        $table = is_string($crud) ? trim($crud) : null;

        $api        = ModulesSync::option($params, 'api') !== null;
        $standalone = ModulesSync::option($params, 'standalone') !== null;
        $generator = new ModuleGenerator();
        $error     = $generator->nameError($name) ?? ($table !== null ? CrudScaffold::tableError($table) : null);

        if ($error === null && $api && $table === null) {
            $error = lang('Core.make.apiNeedsCrud');
        }

        if ($crud === true || $error !== null) {
            CLI::error($error ?? lang('Core.make.invalidTable', ['']));

            return EXIT_ERROR;
        }

        $path = $generator->generate($name, $table, $api, $standalone);
        (new NamespaceFile())->write((new ModuleDiscovery())->folders());

        CLI::write(lang('Core.make.created', [$name, clean_path($path)]), 'green');

        if ($table !== null) {
            $status = $this->crud($name, $table, $path);

            return $status === EXIT_SUCCESS && $api ? $this->api($name, $table, $path) : $status;
        }

        CLI::write(lang('Core.make.next'));

        return EXIT_SUCCESS;
    }

    /**
     * Builds the module of a JSON definition with the code generator.
     */
    private function fromDefinition(string $file, bool $dryRun): int
    {
        // spark runs from public/: a relative path is read from the project root.
        if ($file !== '' && ! is_file($file) && is_file(ROOTPATH . $file)) {
            $file = ROOTPATH . $file;
        }

        $json = $file !== '' && is_file($file) ? file_get_contents($file) : false;

        if (! is_string($json)) {
            CLI::error(lang('Core.make.definitionMissing', [$file]));

            return EXIT_ERROR;
        }

        try {
            $definition = ModuleDefinition::fromJson($json);
            $plan       = (new Engine())->plan($definition);
        } catch (InvalidDefinition $e) {
            CLI::error(lang('Core.make.definitionInvalid'));

            foreach ($e->errors() as $path => $message) {
                CLI::write('  ' . $path . ': ' . $message, 'red');
            }

            return EXIT_ERROR;
        } catch (GeneratorException|\InvalidArgumentException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        $changes = $plan->changes();
        $new     = array_filter($changes, static fn ($f): bool => $f->status() === 'new');

        CLI::write(lang('Core.make.planned', [$definition->name, count($new), count($changes) - count($new)]), 'green');

        foreach ($changes as $path => $planned) {
            CLI::write('  ' . str_pad($planned->status(), 8) . 'modules/' . $path);
        }

        if ($dryRun) {
            CLI::write(lang('Core.make.dryRun'), 'yellow');

            return EXIT_SUCCESS;
        }

        try {
            $written = (new PlanWriter())->write($plan);
        } catch (GeneratorException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(lang('Core.make.written', [count($written)]), 'green');
        CLI::write(lang('Core.make.syncing'));

        if (($error = $this->spark(['modules:sync'])) !== null) {
            CLI::error(lang('Core.make.syncFailed', [$error]));

            return EXIT_ERROR;
        }

        foreach ($plan->notes as $note) {
            CLI::write('- ' . $note);
        }

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
     * Runs kpapy:make for the module's table and rewrites the controller it wrote.
     */
    private function api(string $name, string $table, string $path): int
    {
        $arguments = ApiScaffold::makeArguments($name, $table);

        CLI::write(lang('Core.make.apiGenerating', [implode(' ', array_map(escapeshellarg(...), array_slice($arguments, 1)))]));

        if (($error = $this->spark($arguments)) !== null) {
            CLI::error(lang('Core.make.apiFailed', [$error]));

            return EXIT_ERROR;
        }

        $controller = $path . '/Controllers/' . ApiScaffold::controller($name, $table) . '.php';
        file_put_contents($controller, ApiScaffold::rewriteController((string) file_get_contents($controller)));

        CLI::write(lang('Core.make.apiCreated', [CrudScaffold::resource($name, $table), clean_path($controller), 'api/v1/' . ApiScaffold::path($name, $table)]), 'green');

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
