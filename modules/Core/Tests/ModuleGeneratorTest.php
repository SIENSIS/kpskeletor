<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleGenerator;

/**
 * @internal
 */
final class ModuleGeneratorTest extends CIUnitTestCase
{
    use TempModulesTrait;

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

    public function testGeneratesACompleteValidModule(): void
    {
        $name  = $this->uniqueModuleName();
        $lower = strtolower($name);
        $path  = (new ModuleGenerator($this->tempRoot()))->generate($name);

        foreach ([
            'Config/Module.php', 'Config/Routes.php', 'Controllers/Home.php', 'Views/index.php',
            "Language/ca/{$name}.php", "Language/es/{$name}.php", "Language/en/{$name}.php",
            "Database/Seeds/{$name}Seeder.php", "Database/Seeds/{$name}DemoSeeder.php", "Widgets/{$name}Widget.php", "Tasks/{$name}Task.php",
            "Tests/{$name}ModuleTest.php", "Assets/js/{$lower}.js", 'Assets/css/.gitkeep', 'Assets/img/.gitkeep',
        ] as $file) {
            $this->assertFileExists($path . '/' . $file);
        }

        $migrations = glob($path . "/Database/Migrations/*_Create{$name}Items.php") ?: [];
        $this->assertCount(1, $migrations);

        foreach (glob($path . '/{,*/,*/*/}*.php', GLOB_BRACE) ?: [] as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
            $this->assertSame(0, $code, $file . ': ' . implode("\n", $output));
        }

        $this->assertStringContainsString("'filter' => 'can:{$lower}-main.view'", (string) file_get_contents($path . '/Config/Routes.php'));

        // Same language keys in the three locales.
        $keys = [];

        foreach (['ca', 'es', 'en'] as $locale) {
            $keys[$locale] = array_keys(require $path . "/Language/{$locale}/{$name}.php");
        }

        $this->assertSame($keys['ca'], $keys['es']);
        $this->assertSame($keys['ca'], $keys['en']);

        // The generated manifest is valid.
        service('autoloader')->addNamespace('Modules\\' . $name, $path);
        $class    = "Modules\\{$name}\\Config\\Module";
        $manifest = new $class();
        $this->assertInstanceOf(ModuleManifest::class, $manifest);
        $this->assertSame([], (new ManifestValidator())->validate($manifest, $path));

        // Criterion 2: menu, permissions, a setting, an asset, a widget and a task.
        $this->assertNotSame([], $manifest->menu);
        $this->assertSame(["{$lower}-main.*"], $manifest->defaultGrants['admin']);
        $this->assertArrayHasKey('perPage', $manifest->settings);
        $this->assertCount(1, $manifest->widgets);
        $this->assertCount(1, $manifest->tasks);
        $this->assertSame([], $manifest->csp);
        $this->assertSame([], $manifest->outboundHosts);
        $this->assertStringContainsString("data-component=\"{$lower}/{$lower}\"", (string) file_get_contents($path . '/Views/index.php'));
        $this->assertStringContainsString('export function mount(root, options)', (string) file_get_contents($path . "/Assets/js/{$lower}.js"));
        $this->assertStringContainsString('extends AuthTestCase', (string) file_get_contents($path . "/Tests/{$name}ModuleTest.php"));

        // Criterion 2: the module's CLAUDE.md (purpose, resources and permissions, decisions, open tasks, how to test).
        $claude = (string) file_get_contents($path . '/CLAUDE.md');
        $this->assertStringStartsWith("# {$name} module", $claude);

        foreach (['**Purpose:**', '## Resources and permissions', '## Decisions', '## Open tasks', '## How to test', "`{$lower}-main`", "vendor/bin/phpunit modules/{$name}", "/check-module {$name}"] as $text) {
            $this->assertStringContainsString($text, $claude);
        }

        $this->assertStringNotContainsString('{{', $claude);
        $this->assertFileDoesNotExist($path . '/composer.json');
        $this->assertFileDoesNotExist($path . '/README.md');
    }

    public function testTheModuleClaudeFileDescribesItsCrudAndApi(): void
    {
        $name   = $this->uniqueModuleName();
        $lower  = strtolower($name);
        $path   = (new ModuleGenerator($this->tempRoot()))->generate($name, $lower . '_books', true);
        $claude = (string) file_get_contents($path . '/CLAUDE.md');

        $this->assertStringContainsString("`{$lower}-books` (crud", $claude);
        $this->assertStringContainsString("`api/v1/{$lower}/books`", $claude);
        $this->assertStringContainsString("`{$lower}-books.*`", $claude);
        $this->assertStringNotContainsString('{{', $claude);
    }

    public function testStandaloneModulesGetComposerReadmeAndTheSkeletonRules(): void
    {
        $name  = $this->uniqueModuleName();
        $lower = strtolower($name);
        $path  = (new ModuleGenerator($this->tempRoot()))->generate($name, standalone: true);

        $composer = json_decode((string) file_get_contents($path . '/composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertSame("your-vendor/{$lower}-module", $composer['name']);
        $this->assertSame(['Modules\\' . $name . '\\' => ''], $composer['autoload']['psr-4']);
        $this->assertSame($name, $composer['extra']['kpskeletor']['module']);

        $this->assertStringContainsString("modules:install {$name}.zip", (string) file_get_contents($path . '/README.md'));

        $claude = (string) file_get_contents($path . '/CLAUDE.md');
        $this->assertStringContainsString('standalone repository', $claude);
        $this->assertStringContainsString("git clone <this repository> modules/{$name}", $claude);
        $this->assertStringContainsString('## The skeleton\'s rules', $claude);
        $this->assertStringContainsString('## Module checklist', $claude);

        foreach ([$path . '/composer.json', $path . '/README.md', $path . '/CLAUDE.md'] as $file) {
            $this->assertStringNotContainsString('{{', (string) file_get_contents($file));
        }

        // Still a valid module.
        service('autoloader')->addNamespace('Modules\\' . $name, $path);
        $class = "Modules\\{$name}\\Config\\Module";
        $this->assertSame([], (new ManifestValidator())->validate(new $class(), $path));
    }

    public function testRefusesInvalidReservedAndExistingNames(): void
    {
        $generator = new ModuleGenerator($this->tempRoot());
        $name      = $this->uniqueModuleName();
        $generator->generate($name);

        $this->assertStringContainsString('Invalid module name', (string) $generator->nameError('news'));
        $this->assertStringContainsString('Invalid module name', (string) $generator->nameError('My-Module'));
        $this->assertStringContainsString('reserved', (string) $generator->nameError('Update'));
        $this->assertStringContainsString('already exists', (string) $generator->nameError($name));
        $this->assertNull($generator->nameError('Blog'));

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate('Security');
    }
}
