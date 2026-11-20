<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\GeneratorException;
use Modules\Core\Generator\InvalidDefinition;
use Modules\Core\Generator\Plan;
use Modules\Core\Modules\ModuleGenerator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The code generator's engine (session 42): for a set of definitions the plan matches the files in
 * `Fixtures/Generator/expected/<case>/` (`<path>.expected`, so no tool reads them as code; regenerate them with `GENERATOR_FIXTURES=update`), and the
 * plan of an existing module only adds files and changes the manifest, routes, texts, demo
 * seeder and `CLAUDE.md`, keeping what was there.
 *
 * Cases: `library` (new module: a read-only CRUD; a CRUD with translated fields, rich text, a file
 * field, a custom operation with re-authentication, the own-rows rule, a hidden field and an API),
 * `notes` (the smallest definition) and `zoo-existing` (a table added to an existing module: a
 * column record rule, a public API, a settings menu entry).
 *
 * @internal
 */
final class GeneratorEngineTest extends CIUnitTestCase
{
    use GeneratorTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cases(): iterable
    {
        yield 'new module, two tables' => ['library'];

        yield 'smallest definition' => ['notes'];

        yield 'table added to an existing module' => ['zoo-existing'];
    }

    #[DataProvider('cases')]
    public function testThePlanMatchesItsFixture(string $case): void
    {
        $definition = $this->definition($case);

        if ($definition->existing) {
            $this->existingModule($definition->name);
        }

        $plan     = $this->plan($definition);
        $expected = self::FIXTURES . '/expected/' . $case;
        $summary  = $this->summary($plan);

        if (getenv('GENERATOR_FIXTURES') === 'update') {
            helper('filesystem');

            if (is_dir($expected)) {
                delete_files($expected, true);
            }

            foreach ($plan->changes() as $path => $file) {
                @mkdir(dirname($expected . '/' . $path), 0777, true);
                file_put_contents($expected . '/' . $path . '.expected', $file->content);
            }

            file_put_contents($expected . '/plan.txt', $summary);
            $this->markTestSkipped('Fixtures updated.');
        }

        $this->assertSame((string) file_get_contents($expected . '/plan.txt'), $summary);

        foreach ($plan->changes() as $path => $file) {
            $this->assertStringEqualsFile($expected . '/' . $path . '.expected', $file->content, $path);
        }
    }

    public function testAnExistingModuleKeepsWhatItHadAndOnlyAddsMigrations(): void
    {
        $definition = $this->definition('zoo-existing');
        $path       = $this->existingModule('Zoo');
        $before     = (string) file_get_contents($path . '/Config/Module.php');
        $plan       = $this->plan($definition);

        foreach ($plan->changes() as $file) {
            if ($file->status() === 'changed') {
                $this->assertContains(basename($file->path), ['Module.php', 'Routes.php', 'Zoo.php', 'ZooDemoSeeder.php', 'CLAUDE.md'], $file->path);
            }

            if (str_contains($file->path, '/Database/Migrations/')) {
                $this->assertSame('new', $file->status(), 'Migrations are only ever added.');
            }
        }

        $manifest = $plan->files['Zoo/Config/Module.php'];
        $this->assertSame($before, $manifest->original);

        // Every line of the original manifest is still there; a line of an array that received
        // items keeps everything before its closing bracket.
        foreach (array_diff(explode("\n", $before), explode("\n", $manifest->content)) as $line) {
            $this->assertStringContainsString(rtrim(substr($line, 0, (int) strrpos($line, ']')), '['), $manifest->content, $line);
        }

        $this->assertStringContainsString("'animals' => [\n            'label' => 'Zoo.res.animals',", $manifest->content);
        $this->assertStringContainsString("'zoo-animals.*'", $manifest->content);
    }

    public function testAnInvalidDefinitionIsRefusedWithItsErrors(): void
    {
        $this->expectException(InvalidDefinition::class);

        $this->plan(ModuleDefinition::fromArray(['module' => ['name' => 'lower'], 'tables' => [['name' => 'x']]]));
    }

    public function testAFileThatExistsIsNeverOverwritten(): void
    {
        $path = $this->existingModule('Zoo');
        @mkdir($path . '/Controllers', 0777, true);
        file_put_contents($path . '/Controllers/Animals.php', '<?php // mine');

        $this->expectException(GeneratorException::class);

        $this->plan($this->definition('zoo-existing'));
    }

    public function testThePreviewAndTheWriteAgreeAcrossASecond(): void
    {
        $definition = $this->definition('notes');
        $first      = $this->plan($definition, '2026-11-20 10:00:00');
        $second     = $this->plan($definition, '2026-11-20 10:00:01');

        $this->assertNotSame(array_keys($first->files), array_keys($second->files), 'Migration names carry the time.');
        $this->assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testTheDefinitionSurvivesAJsonRoundTrip(): void
    {
        foreach (['library', 'notes', 'zoo-existing'] as $case) {
            $definition = $this->definition($case);
            $again      = ModuleDefinition::fromJson($definition->toJson());

            $this->assertSame($definition->toArray(), $again->toArray(), $case);
        }
    }

    public function testAModuleWithoutTablesIsMakeModulesOutput(): void
    {
        Time::setTestNow('2026-11-20 10:00:00');

        try {
            $expected = (new ModuleGenerator($this->tempRoot()))->files('Plain');
            $plan     = $this->plan(ModuleDefinition::fromArray(['module' => ['name' => 'Plain']]));
        } finally {
            Time::setTestNow();
        }

        $this->assertCount(count($expected), $plan->files);

        foreach ($expected as $file => $content) {
            $this->assertArrayHasKey('Plain/' . $file, $plan->files);
            $this->assertSame($content, $plan->files['Plain/' . $file]->content, $file);
        }
    }

    private function existingModule(string $name): string
    {
        Time::setTestNow('2026-11-01 09:00:00');

        try {
            return (new ModuleGenerator($this->tempRoot()))->generate($name);
        } finally {
            Time::setTestNow();
        }
    }

    private function summary(Plan $plan): string
    {
        $lines = [];

        foreach ($plan->files as $path => $file) {
            $lines[] = str_pad($file->status(), 10) . $path;
        }

        return implode("\n", $lines) . "\n";
    }
}
