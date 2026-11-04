<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Theme\ThemeNotFoundException;
use Modules\Core\Theme\ThemeRegistry;

/**
 * Theme folders, manifests and parent chains.
 *
 * @internal
 */
final class ThemeRegistryTest extends CIUnitTestCase
{
    use ThemeFixturesTrait;

    protected function tearDown(): void
    {
        $this->removeThemeFixtures();
        parent::tearDown();
    }

    public function testSlugsAndNamespaces(): void
    {
        $this->assertTrue(ThemeRegistry::isValidSlug('default'));
        $this->assertTrue(ThemeRegistry::isValidSlug('tasks-demo'));
        $this->assertFalse(ThemeRegistry::isValidSlug('Tasks'));
        $this->assertFalse(ThemeRegistry::isValidSlug('../etc'));
        $this->assertFalse(ThemeRegistry::isValidSlug('a--b'));
        $this->assertSame('Themes\TasksDemo', ThemeRegistry::namespaceOf('tasks-demo'));
        $this->assertSame('tasks-demo', ThemeRegistry::slugFromName('TasksDemo'));
        $this->assertSame('corporate', ThemeRegistry::slugFromName('Corporate'));
        $this->assertSame('tasks-demo', ThemeRegistry::slugFromName('tasks-demo'));
    }

    public function testTheDefaultThemeIsFound(): void
    {
        $registry = new ThemeRegistry();

        $this->assertContains('default', $registry->slugs());
        $this->assertSame(['default'], $registry->chain('default'));
        $this->assertSame('Default', $registry->get('default')->name);
    }

    public function testChainGoesFromChildToRoot(): void
    {
        $base   = $this->uniqueSlug('base');
        $middle = $this->uniqueSlug('mid');
        $child  = $this->uniqueSlug('child');
        $this->makeBaseTheme($base);
        $this->makeTheme($middle, $base);
        $this->makeTheme($child, $middle);

        $registry = new ThemeRegistry($this->themesRoot());

        $this->assertSame([$child, $middle, $base], $registry->chain($child));
        $this->assertEqualsCanonicalizing([$base, $middle, $child], $registry->slugs());
    }

    public function testMissingThemeOrParentIsRefused(): void
    {
        $registry = new ThemeRegistry($this->themesRoot());

        $this->expectException(ThemeNotFoundException::class);
        $registry->get('nope');
    }

    public function testMissingParentBreaksTheChain(): void
    {
        $child = $this->uniqueSlug('orphan');
        $this->makeTheme($child, 'missing-parent');

        $this->expectException(ThemeNotFoundException::class);
        (new ThemeRegistry($this->themesRoot()))->chain($child);
    }

    public function testParentLoopsAreRefused(): void
    {
        $a = $this->uniqueSlug('loop');
        $b = $this->uniqueSlug('loop');
        $this->makeTheme($a, $b);
        $this->makeTheme($b, $a);

        $this->expectException(ThemeNotFoundException::class);
        (new ThemeRegistry($this->themesRoot()))->chain($a);
    }

    public function testInvalidSlugIsNeverLoaded(): void
    {
        $registry = new ThemeRegistry($this->themesRoot());

        $this->assertFalse($registry->exists('../themes/default'));
        $this->expectException(ThemeNotFoundException::class);
        $registry->get('../default');
    }
}
