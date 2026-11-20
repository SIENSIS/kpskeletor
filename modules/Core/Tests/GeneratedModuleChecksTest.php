<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Engine;
use Modules\Core\Generator\GeneratorContext;
use Modules\Core\Generator\PlanWriter;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\NamespaceFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * A module generated from the `library` definition (two tables: a read-only CRUD, and a CRUD with
 * translated fields, rich text, a file field, custom operations — one behind re-authentication —,
 * the own-rows rule, a hidden field and an API), and a table added to an existing module
 * (`zoo-existing`: a column record rule, a public API, a settings menu entry), are written into `modules/` under a unique name and
 * must pass, in their own processes: `php -l`, php-cs-fixer, PHPStan, `csp:check` and **its own
 * generated tests** on SQLite (permissions, forged posts, its rules, API scopes, migrations
 * up/down/up). The module and its namespace are removed afterwards.
 *
 * @internal
 */
#[Group('generator')]
final class GeneratedModuleChecksTest extends CIUnitTestCase
{
    private string $name = '';

    private string $namespaces = '';

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->namespaces = (string) file_get_contents(APPPATH . 'Config/ModuleNamespaces.php');
    }

    protected function tearDown(): void
    {
        if ($this->name !== '' && is_dir(ROOTPATH . 'modules/' . $this->name)) {
            helper('filesystem');
            delete_files(ROOTPATH . 'modules/' . $this->name, true, false, true);
            rmdir(ROOTPATH . 'modules/' . $this->name);
        }

        file_put_contents(APPPATH . 'Config/ModuleNamespaces.php', $this->namespaces);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function definitions(): iterable
    {
        yield 'new module' => ['library', 'Library'];

        yield 'table added to an existing module' => ['zoo-existing', 'Zoo'];

        yield 'the definition of tutorial 15' => [ROOTPATH . 'docs/tutorials/files/tasks2.definition.json', 'Tasks2'];
    }

    #[DataProvider('definitions')]
    public function testTheGeneratedModulePassesTheChecksAndItsOwnTests(string $fixture, string $module): void
    {
        $this->name = 'Gen' . strtoupper(bin2hex(random_bytes(3)));
        $lower      = strtolower($this->name);
        $json       = (string) file_get_contents(str_ends_with($fixture, '.json') ? $fixture : __DIR__ . '/Fixtures/Generator/' . $fixture . '.json');
        $json       = str_replace(['"' . $module . '"', strtolower($module) . '_'], ['"' . $this->name . '"', $lower . '_'], $json);
        $definition = ModuleDefinition::fromJson($json);

        if ($definition->existing) {
            (new ModuleGenerator())->generate($this->name);
            (new NamespaceFile())->write((new ModuleDiscovery())->folders());
        }

        // The test database is empty: the core tables are assumed (`users` for foreign keys).
        $context = new GeneratorContext(tableExists: static fn (string $table): bool => $table === 'users', groups: ['superadmin', 'admin', 'editor', 'user']);
        $plan    = (new Engine($context))->plan($definition);
        $written = (new PlanWriter())->write($plan, $plan->fingerprint());
        $path    = ROOTPATH . 'modules/' . $this->name;

        $this->assertNotSame([], $written);
        $this->assertStringContainsString("'Modules\\{$this->name}'", (string) file_get_contents(APPPATH . 'Config/ModuleNamespaces.php'));

        foreach ($written as $file) {
            if (str_ends_with($file, '.php')) {
                $this->assertSame('', $this->command(['php', '-l', ROOTPATH . 'modules/' . $file], quietOk: true), $file);
            }
        }

        $this->assertSame('', $this->command([PHP_BINARY, ROOTPATH . 'vendor/bin/php-cs-fixer', 'fix', '--dry-run', '--using-cache=no', '--sequential', $path], quietOk: true), 'php-cs-fixer');
        $this->assertSame('', $this->command([PHP_BINARY, ROOTPATH . 'vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G', $path], quietOk: true), 'PHPStan');
        $this->assertSame('', $this->command([PHP_BINARY, ROOTPATH . 'spark', 'csp:check'], quietOk: true), 'csp:check');
        $this->assertSame('', $this->command([PHP_BINARY, ROOTPATH . 'vendor/bin/phpunit', '--no-progress', $path], quietOk: true), 'Its own tests');
    }

    /**
     * Runs a command; '' when it succeeds, otherwise its output.
     *
     * @param list<string> $command
     */
    private function command(array $command, bool $quietOk): string
    {
        $output = [];
        exec(implode(' ', array_map(escapeshellarg(...), $command)) . ' 2>&1', $output, $status);

        return $status === 0 && $quietOk ? '' : implode("\n", $output);
    }
}
