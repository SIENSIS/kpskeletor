<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use Modules\Core\Theme\Regions;
use Modules\Core\Theme\ThemeManager;
use Modules\Core\Theme\ThemeRegistry;

/**
 * Active theme per area, fallback, assets, regions, and a child theme of the default theme that
 * overrides one view and one colour without copying the rest.
 *
 * @internal
 */
final class ThemeManagerTest extends CIUnitTestCase
{
    use ThemeFixturesTrait;

    protected function tearDown(): void
    {
        $this->removeThemeFixtures();
        $this->resetServices();
        parent::tearDown();
    }

    /**
     * @param array<string, string> $settings
     */
    private function manager(array $settings = [], ?string $root = null): ThemeManager
    {
        return new ThemeManager(
            new ThemeRegistry($root),
            new Regions(),
            static fn (string $key): ?string => $settings[$key] ?? null,
        );
    }

    public function testEachAreaUsesItsSetting(): void
    {
        $child = $this->uniqueSlug('area');
        $this->makeDefaultChild($child);

        $manager = $this->manager(['Theme.backend' => $child]);

        $this->assertSame($child, $manager->slug('backend'));
        $this->assertSame([$child, 'default'], $manager->chain('backend'));
        $this->assertSame('default', $manager->slug('public'));
    }

    public function testMissingThemeFallsBackToDefault(): void
    {
        $manager = $this->manager(['Theme.public' => 'does-not-exist', 'Theme.backend' => '../../etc']);

        $this->assertSame('default', $manager->slug('public'));
        $this->assertSame('default', $manager->slug('backend'));
    }

    public function testUnknownAreaAndLayoutAreRefused(): void
    {
        $manager = $this->manager();

        $this->assertSame('Themes\Default\Views\layouts\print', $manager->layout('print'));

        $this->expectException(InvalidArgumentException::class);
        $manager->layout('fancy');
    }

    public function testUnknownAreaIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager()->setArea('admin');
    }

    public function testChildAssetsLoadAfterTheParentsAndNeverHideThem(): void
    {
        $child = $this->uniqueSlug('assets');
        $this->makeDefaultChild($child, "public array \$styles = ['css/theme.css'];", [
            'Assets/css/theme.css' => ':root { --kp-sidebar-bg: #312e81; }',
        ]);

        $manager = $this->manager(['Theme.public' => $child]);
        $styles  = $manager->styles('public');

        $this->assertCount(4, $styles);
        $this->assertStringContainsString('assets/vendor/bootstrap/css/bootstrap.min.css', $styles[0]);
        $this->assertStringContainsString('themes/default/css/theme.css', $styles[2]);
        $this->assertStringContainsString('themes/' . $child . '/css/theme.css', $styles[3]);
        $this->assertStringContainsString('bootstrap.bundle.min.js', $manager->scripts('public')[0]);
        $this->assertNull($manager->assetUrl('../.env', 'public'));
        $this->assertNull($manager->assetUrl('css/missing.css', 'public'));
    }

    public function testRegionsArePrintedOnlyWhenTheThemeDeclaresThem(): void
    {
        $manager = $this->manager();
        $manager->regions()->add('sidebar', '<b>second</b>', 20);
        $manager->regions()->add('sidebar', '<b>first</b>', 10);
        $manager->regions()->add('nowhere', '<b>never</b>');

        $this->assertSame("<b>first</b>\n<b>second</b>", $manager->region('public', 'sidebar', 'public'));
        $this->assertSame('', $manager->region('public', 'nowhere', 'public'));
        $this->assertSame('', $manager->region('print', 'sidebar', 'public'));
        $this->assertArrayHasKey('primary', $manager->menuLocations('public'));
    }

    public function testChildThemeOverridesOneViewAndOneColourAndInheritsTheRest(): void
    {
        $child = $this->uniqueSlug('corp');
        $this->makeDefaultChild($child, "public array \$options = ['primaryColor' => ['default' => '#6f42c1']];", [
            'Views/components/empty_state.php' => '<div class="child-empty"><?= esc($title) ?></div>',
        ]);

        $manager = $this->manager(['Theme.backend' => $child]);
        $manager->setArea('backend');
        $this->injectTheme($manager);

        $html = html_entity_decode($manager->render('Modules\Core\Views\preview\page', [
            'layout' => $manager->layout('backend'),
            'title'  => 'Child test',
            'themes' => [['slug' => $child, 'name' => $child], ['slug' => 'default', 'name' => 'Default']],
        ]));

        // The parent's layout and partials are used (nothing copied)…
        $this->assertStringContainsString('class="layout-backend', $html);
        $this->assertStringContainsString('id="backend-sidebar"', $html);
        $this->assertStringContainsString('themes/default/css/theme.css', $html);
        // …with the child's colour…
        $this->assertStringContainsString('--bs-primary: #6f42c1;', $html);
        $this->assertStringContainsString('--bs-primary-rgb: 111, 66, 193;', $html);
        // …and the child's view where it overrides one.
        $component = theme_component('empty_state', ['title' => 'Nothing']);
        $this->assertStringContainsString('<div class="child-empty">Nothing</div>', $component);
        $this->assertStringNotContainsString('empty-state text-center', $component);
        // Other components still come from the Core.
        $this->assertStringContainsString('badge text-bg-success', ui_badge('Ok', 'success'));
    }

    public function testDefaultThemeRendersEveryLayout(): void
    {
        $manager = $this->manager();
        $this->injectTheme($manager);

        foreach (['backend', 'public', 'auth', 'blank', 'print'] as $layout) {
            $manager->setArea($layout === 'backend' ? 'backend' : 'public');
            $html = $manager->render('Modules\Core\Views\preview\page', [
                'layout' => $manager->layout($layout),
                'title'  => 'Layout ' . $layout,
                'themes' => [['slug' => 'default', 'name' => 'Default']],
            ]);

            $this->assertStringContainsString('class="layout-' . $layout, $html, $layout);
            $this->assertStringContainsString('<title>Layout ' . $layout, $html, $layout);
            $this->assertStringNotContainsString(' style="', $html, $layout);
            $this->assertStringNotContainsString(' onclick=', $html, $layout);
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(?:src=|type="application\/json"|type="importmap"))[^>]*>/', $html, $layout);
        }
    }

    public function testForceUsesAnotherThemeForTheRequest(): void
    {
        $child = $this->uniqueSlug('forced');
        $this->makeDefaultChild($child);

        $manager = $this->manager();
        $manager->force('public', $child);

        $this->assertSame($child, $manager->slug('public'));
        $this->assertSame('default', $manager->slug('backend'));
    }

    private function injectTheme(ThemeManager $manager): void
    {
        \Config\Services::injectMock('theme', $manager);
    }
}
