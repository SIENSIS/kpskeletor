<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\View\Exceptions\ViewException;
use Modules\Core\Theme\ThemeRegistry;
use Modules\Core\Theme\ViewResolver;

/**
 * View resolution order: active theme override → parent theme override → module view → Core
 * standard view.
 *
 * @internal
 */
final class ViewResolverTest extends CIUnitTestCase
{
    use ThemeFixturesTrait;
    use TempModulesTrait;

    private string $parent = '';
    private string $child  = '';
    private string $core   = '';
    private string $module = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = $this->uniqueSlug('parent');
        $this->child  = $this->uniqueSlug('child');
        $this->makeBaseTheme($this->parent, '', [
            'Views/components/shared.php'      => 'parent-shared',
            'Views/components/parentonly.php'  => 'parent-only',
            'Views/overrides/Mod/both.php'     => 'parent-override',
            'Views/overrides/Mod/parentmod.php' => 'parent-override-only',
        ]);
        $this->makeTheme($this->child, $this->parent, '', [
            'Views/components/shared.php'  => 'child-shared',
            'Views/overrides/Mod/both.php' => 'child-override',
        ]);

        // A Core standard views folder and a module with its own views.
        $this->core = $this->tempRoot() . '/CoreViews';
        mkdir($this->core . '/components', 0777, true);
        file_put_contents($this->core . '/components/shared.php', 'core-shared');
        file_put_contents($this->core . '/components/coreonly.php', 'core-only');
        file_put_contents($this->core . '/fallback.php', 'core-fallback');

        $this->module = $this->uniqueModuleName();
        mkdir($this->tempRoot() . '/' . $this->module . '/Views', 0777, true);
        file_put_contents($this->tempRoot() . '/' . $this->module . '/Views/own.php', 'module-own');
        service('autoloader')->addNamespace('Modules\\' . $this->module, $this->tempRoot() . '/' . $this->module);
    }

    protected function tearDown(): void
    {
        $this->removeThemeFixtures();
        $this->removeTempRoot();
        parent::tearDown();
    }

    private function resolver(): ViewResolver
    {
        $registry = new ThemeRegistry($this->themesRoot());
        $themes   = [];

        foreach ($registry->chain($this->child) as $slug) {
            $themes[] = ['namespace' => ThemeRegistry::namespaceOf($slug), 'path' => $registry->path($slug)];
        }

        return new ViewResolver($themes, $this->core);
    }

    public function testStandardViewsGoActiveThemeParentCore(): void
    {
        $resolver = $this->resolver();
        $child    = ThemeRegistry::namespaceOf($this->child);
        $parent   = ThemeRegistry::namespaceOf($this->parent);

        $this->assertSame($child . '\Views\components\shared', $resolver->resolve('components/shared'));
        $this->assertSame($parent . '\Views\components\parentonly', $resolver->resolve('components/parentonly'));
        $this->assertSame('Modules\Core\Views\components\coreonly', $resolver->resolve('components/coreonly'));
        $this->assertSame($parent . '\Views\layouts\backend', $resolver->resolve('layouts/backend'));
    }

    public function testModuleViewsGoOverrideParentOverrideModuleCore(): void
    {
        // The module "Mod" has no files: the overrides are found first.
        $resolver = $this->resolver();
        $child    = ThemeRegistry::namespaceOf($this->child);
        $parent   = ThemeRegistry::namespaceOf($this->parent);

        $this->assertSame($child . '\Views\overrides\Mod\both', $resolver->resolve('Modules\Mod\Views\both'));
        $this->assertSame($parent . '\Views\overrides\Mod\parentmod', $resolver->resolve('Modules\Mod\Views\parentmod'));

        // A real module view without override is the module's own view.
        $own = 'Modules\\' . $this->module . '\Views\own';
        $this->assertSame($own, $resolver->resolve($own));

        // A module view that exists nowhere falls back to the Core standard view of that path.
        $this->assertSame('Modules\Core\Views\fallback', $resolver->resolve('Modules\\' . $this->module . '\Views\fallback'));
    }

    public function testRenderedContentFollowsTheOrder(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('child-shared', $this->render($resolver->resolve('components/shared')));
        $this->assertSame('parent-only', $this->render($resolver->resolve('components/parentonly')));
        $this->assertSame('child-override', $this->render($resolver->resolve('Modules\Mod\Views\both')));
        $this->assertSame('module-own', $this->render($resolver->resolve('Modules\\' . $this->module . '\Views\own')));
    }

    /** Renders a view without the debug comments a development .env adds. */
    private function render(string $view): string
    {
        return trim((string) preg_replace('/<!-- DEBUG-VIEW [^>]*-->/', '', view($view, [], ['saveData' => false])));
    }

    public function testOtherNamespacesAreLeftAlone(): void
    {
        $this->assertSame('App\Views\welcome_message', $this->resolver()->resolve('App\Views\welcome_message'));
    }

    public function testMissingStandardViewThrows(): void
    {
        $this->expectException(ViewException::class);
        $this->resolver()->resolve('components/nowhere');
    }

    public function testPathTricksAreRefused(): void
    {
        $this->expectException(ViewException::class);
        $this->resolver()->resolve('../../app/Config/App');
    }
}
