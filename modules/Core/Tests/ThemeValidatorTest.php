<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Theme\ThemeRegistry;
use Modules\Core\Theme\ThemeValidator;

/**
 * Theme manifests are checked (by modules:sync and make:theme) with messages naming the key.
 *
 * @internal
 */
final class ThemeValidatorTest extends CIUnitTestCase
{
    use ThemeFixturesTrait;

    protected function tearDown(): void
    {
        $this->removeThemeFixtures();
        parent::tearDown();
    }

    public function testTheShippedThemesAreValid(): void
    {
        $this->assertSame([], (new ThemeValidator())->validateAll());
    }

    public function testAValidChildNeedsNoLayouts(): void
    {
        $base  = $this->uniqueSlug('base');
        $child = $this->uniqueSlug('child');
        $this->makeBaseTheme($base);
        $this->makeTheme($child, $base, "public array \$options = ['logo' => ['type' => 'string', 'default' => '']];");

        $this->assertSame([], (new ThemeValidator(new ThemeRegistry($this->themesRoot())))->validate($child));
    }

    public function testErrorsNameTheKey(): void
    {
        $slug = $this->uniqueSlug('broken');
        $this->makeTheme($slug, null, <<<'PHP'
            public string $version = 'one';
            public array $layouts = ['backend', 'fancy'];
            public array $regions = ['public' => ['Bad Region']];
            public array $menuLocations = ['Main' => 'x'];
            public array $pageTemplates = ['Bad Template' => 'x'];
            public array $styles = ['../secret.css', 'css/missing.css', 'https://cdn.example.com/x.css'];
            public array $options = [
                'primaryColor' => ['type' => 'string', 'default' => '#000', 'css' => '--bs-primary'],
                'badColour' => ['type' => 'color', 'default' => 'red'],
                'what' => ['type' => 'nope'],
            ];
            PHP);

        $errors = implode("\n", (new ThemeValidator(new ThemeRegistry($this->themesRoot())))->validate($slug));

        $this->assertStringContainsString('$version must be a semantic version', $errors);
        $this->assertStringContainsString('$layouts must be one of', $errors);
        $this->assertStringContainsString('declares a layout whose file is missing: Views/layouts/backend.php', $errors);
        $this->assertStringContainsString('misses the layout "auth"', $errors);
        $this->assertStringContainsString("\$regions['public'] has an invalid name \"Bad Region\"", $errors);
        $this->assertStringContainsString("\$menuLocations['Main']", $errors);
        $this->assertStringContainsString("\$pageTemplates['Bad Template']", $errors);
        $this->assertStringContainsString('has an invalid path "../secret.css"', $errors);
        $this->assertStringContainsString('has an invalid path "https://cdn.example.com/x.css"', $errors);
        $this->assertStringContainsString('refers to a file that does not exist: css/missing.css', $errors);
        $this->assertStringContainsString("\$options['primaryColor']['css']", $errors);
        $this->assertStringContainsString("\$options['badColour']['default'] must be a hex colour", $errors);
        $this->assertStringContainsString("\$options['what']['type'] must be one of", $errors);
    }

    public function testMissingParentIsReported(): void
    {
        $slug = $this->uniqueSlug('orphan');
        $this->makeTheme($slug, 'nowhere');

        $errors = (new ThemeValidator(new ThemeRegistry($this->themesRoot())))->validate($slug);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('"nowhere" does not exist', $errors[0]);
    }
}
