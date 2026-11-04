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
            "Tests/{$name}ModuleTest.php", 'Assets/js/.gitkeep', 'Assets/css/.gitkeep', 'Assets/img/.gitkeep',
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
