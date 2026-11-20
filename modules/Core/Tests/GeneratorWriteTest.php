<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Generator\GeneratorException;
use Modules\Core\Generator\PlanWriter;
use Modules\Core\Generator\SourceEditor;
use Modules\Core\Modules\ModuleGenerator;

/**
 * The source editor (nikic/php-parser, D-42-01) and the plan writer: format-preserving edits,
 * refused conflicts, and writes refused when a file changed after the preview.
 *
 * @internal
 */
final class GeneratorWriteTest extends CIUnitTestCase
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

    public function testTheEditorAddsItemsAndKeepsTheRestAsWritten(): void
    {
        $source = <<<'PHP'
            <?php

            final class Module
            {
                public string $version  = '1.0.0';   // aligned on purpose

                /** Resources. */
                public array $resources = [
                    'main' => ['label' => 'X.res.main', 'type' => 'page'],   // a comment
                ];

                public array $defaultGrants = ['admin' => ['x-main.*']];

                public array $api = [];
            }
            PHP;

        $editor = new SourceEditor();
        $result = $editor->addArrayItems($source, 'resources', ['items' => ['label' => 'X.res.items', 'type' => 'crud']]);
        $result = $editor->addArrayItems($result, 'defaultGrants', ['admin' => ['x-items.*', 'x-main.*'], 'user' => ['x-items.list']]);
        $result = $editor->addArrayItems($result, 'api', ['items' => ['path' => 'x/items']]);
        $result = $editor->setProperty($result, 'author', 'string', 'Me', 'version');

        $this->assertStringContainsString("public string \$version  = '1.0.0';   // aligned on purpose", $result);
        $this->assertStringContainsString("'main' => ['label' => 'X.res.main', 'type' => 'page'],   // a comment\n        'items' => ['label' => 'X.res.items', 'type' => 'crud'],", $result);
        $this->assertStringContainsString("['admin' => ['x-main.*', 'x-items.*'], 'user' => ['x-items.list']]", $result);
        $this->assertStringContainsString("public array \$api = [\n        'items' => ['path' => 'x/items'],\n    ];", $result);
        $this->assertStringContainsString("public string \$author = 'Me';", $result);
        $this->assertSame("<?php\nreturn ['title' => 'Old', 'more' => 'New'];\n", $editor->addArrayItems("<?php\nreturn ['title' => 'Old'];\n", '', ['title' => 'New', 'more' => 'New']), 'Existing keys are never overwritten.');
        $this->assertNotFalse(@token_get_all($result));
    }

    public function testTheEditorRefusesConflictsAndBrokenFiles(): void
    {
        $editor = new SourceEditor();

        try {
            $editor->addArrayItems("<?php\nreturn ['books' => 'Books'];\n", '', ['books' => ['singular' => 'Book']]);
            $this->fail('A string key cannot become an array.');
        } catch (GeneratorException $e) {
            $this->assertStringContainsString('books', $e->getMessage());
        }

        $this->expectException(GeneratorException::class);
        $editor->addArrayItems("<?php\nreturn [\n", '', ['a' => 'b']);
    }

    public function testTheEditorAddsMethodsAndStatements(): void
    {
        $editor = new SourceEditor();
        $source = "<?php\n\nfinal class Seeder\n{\n    public function load(): void\n    {\n    }\n}\n";
        $result = $editor->addMethod($source, "    private function more(): void\n    {\n    }");
        $result = $editor->addToMethod($result, 'load', '$this->more();');

        $this->assertStringContainsString("public function load(): void\n    {\n        \$this->more();\n    }", $result);
        $this->assertTrue($editor->hasMethod($result, 'more'));
    }

    public function testThePlanIsWrittenAndAChangedFileRefusesIt(): void
    {
        $this->existing();
        $plan   = $this->plan($this->definition('zoo-existing'));
        $writer = new PlanWriter($this->context());
        $root   = $this->tempRoot();

        // Changed after the preview: refused, nothing written.
        file_put_contents($root . '/Zoo/Config/Routes.php', "\n// edited by hand\n", FILE_APPEND);

        try {
            $writer->write($plan, $plan->fingerprint());
            $this->fail('A file changed after the preview.');
        } catch (GeneratorException) {
            $this->assertFileDoesNotExist($root . '/Zoo/Controllers/Animals.php');
        }

        // A new plan sees the edit and is written; its fingerprint is checked.
        $plan = $this->plan($this->definition('zoo-existing'));

        try {
            $writer->write($plan, str_repeat('0', 64));
            $this->fail('Another fingerprint.');
        } catch (GeneratorException) {
            $this->assertFileDoesNotExist($root . '/Zoo/Controllers/Animals.php');
        }

        $written = $writer->write($plan, $plan->fingerprint());

        $this->assertContains('Zoo/Controllers/Animals.php', $written);
        $this->assertStringContainsString('// edited by hand', (string) file_get_contents($root . '/Zoo/Config/Routes.php'));
        $this->assertStringContainsString("CrudFactory::routes(\$routes, 'zoo-animals'", (string) file_get_contents($root . '/Zoo/Config/Routes.php'));
        $this->assertSame([], glob($root . '/Zoo/*/*.tmp-*') ?: [], 'No temporary file is left.');

        foreach ($written as $path) {
            exec('php -l ' . escapeshellarg($root . '/' . $path) . ' 2>&1', $output, $code);
            $this->assertSame(0, str_ends_with($path, '.php') ? $code : 0, $path);
        }

        // The same plan again: the files changed, so it is refused.
        $this->expectException(GeneratorException::class);
        $writer->write($plan, $plan->fingerprint());
    }

    private function existing(): void
    {
        (new ModuleGenerator($this->tempRoot()))->generate('Zoo');
    }
}
