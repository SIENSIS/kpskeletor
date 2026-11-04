<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Theme\ThemeAssetPublisher;
use Modules\Core\Theme\ThemeGenerator;
use Modules\Core\Theme\ThemeRegistry;
use Modules\Core\Theme\ThemeValidator;

/**
 * make:theme, the generator and the theme asset publisher.
 *
 * @internal
 */
final class ThemeGeneratorTest extends CIUnitTestCase
{
    use StreamFilterTrait;
    use ThemeFixturesTrait;

    protected function tearDown(): void
    {
        $this->removeThemeFixtures();
        parent::tearDown();
    }

    public function testNamesAndErrors(): void
    {
        $generator = new ThemeGenerator(new ThemeRegistry($this->themesRoot()));

        $this->assertSame('tasks-demo', ThemeGenerator::slug('TasksDemo'));
        $this->assertNotNull($generator->error('bad_name', 'default'));
        $this->assertNotNull($generator->error('../x', 'default'));
        $this->assertNotNull($generator->error('Fine', 'missing'));
        $this->assertNull($generator->error('Fine', null));
    }

    public function testChildThemeIsValidAndMinimal(): void
    {
        $base = $this->uniqueSlug('base');
        $this->makeBaseTheme($base);
        $registry = new ThemeRegistry($this->themesRoot());
        $name     = 'Corp' . strtoupper(bin2hex(random_bytes(3)));
        $slug     = ThemeGenerator::slug($name);

        $path = (new ThemeGenerator($registry))->generate($name, $base);

        $this->assertFileExists($path . '/Theme.php');
        $this->assertFileExists($path . '/Assets/css/theme.css');
        $this->assertFileExists($path . '/Views/overrides/.gitkeep');
        $this->assertFileExists($path . '/Language/ca/Theme' . ThemeRegistry::studly($slug) . '.php');
        $this->assertFileDoesNotExist($path . '/Views/layouts/backend.php');
        $this->assertSame([], (new ThemeValidator($registry))->validate($slug));
        $this->assertSame([$slug, $base], $registry->chain($slug));
    }

    public function testStandaloneThemeGetsEveryLayout(): void
    {
        $registry = new ThemeRegistry($this->themesRoot());
        $name     = 'Solo' . strtoupper(bin2hex(random_bytes(3)));
        $slug     = ThemeGenerator::slug($name);

        $path = (new ThemeGenerator($registry))->generate($name, null);

        foreach (['backend', 'public', 'auth', 'blank', 'print'] as $layout) {
            $this->assertFileExists($path . "/Views/layouts/{$layout}.php");
        }

        $this->assertSame([], (new ThemeValidator($registry))->validate($slug));
    }

    public function testMakeThemeCommand(): void
    {
        $name = 'Cmd' . strtoupper(bin2hex(random_bytes(3)));
        $slug = ThemeGenerator::slug($name);
        $this->realThemeFixtures[] = $slug;

        command('make:theme ' . $name . ' --parent=default');
        $output = $this->getStreamFilterBuffer();

        $this->assertStringContainsString('Theme ' . $slug . ' created', $output);
        $this->assertFileExists(ROOTPATH . 'themes/' . $slug . '/Theme.php');
        $this->assertFileExists(FCPATH . 'themes/' . $slug . '/css/theme.css');

        $this->resetStreamFilterBuffer();
        command('make:theme ' . $name);
        $this->assertStringContainsString('already exists', $this->getStreamFilterBuffer());

        $this->resetStreamFilterBuffer();
        command('make:theme Other' . $name . ' --parent=nowhere');
        $this->assertStringContainsString('The parent theme "nowhere" does not exist', $this->getStreamFilterBuffer());
    }

    public function testPublisherCopiesChangesAndRemovesStaleFiles(): void
    {
        $slug   = $this->uniqueSlug('pub');
        $target = $this->themesRoot() . '/public';
        $this->makeBaseTheme($slug, '', [
            'Assets/css/theme.css' => 'a{}',
            'Assets/img/logo.png'  => 'png',
            'Assets/evil.php'      => '<?php echo 1;',
        ]);
        $publisher = new ThemeAssetPublisher(new ThemeRegistry($this->themesRoot()), $target);

        $this->assertSame(2, $publisher->publish($slug));
        $this->assertFileExists("{$target}/{$slug}/css/theme.css");
        $this->assertFileDoesNotExist("{$target}/{$slug}/evil.php");
        $this->assertSame(0, $publisher->publish($slug));

        unlink($this->themesRoot() . "/{$slug}/Assets/img/logo.png");
        file_put_contents($this->themesRoot() . "/{$slug}/Assets/css/theme.css", 'b{}');

        $this->assertSame(2, $publisher->publish($slug));
        $this->assertSame('b{}', file_get_contents("{$target}/{$slug}/css/theme.css"));
        $this->assertDirectoryDoesNotExist("{$target}/{$slug}/img");
        $this->assertSame(0, $publisher->publish('../etc'));
    }
}
