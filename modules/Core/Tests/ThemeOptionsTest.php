<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Theme\ThemeManifest;
use Modules\Core\Theme\ThemeOptions;

/**
 * Theme options: inherited definitions, saved values, CSS variables (validated hex only).
 *
 * @internal
 */
final class ThemeOptionsTest extends CIUnitTestCase
{
    private function parent(): ThemeManifest
    {
        return new class () extends ThemeManifest {
            public array $options = [
                'primaryColor' => ['type' => 'color', 'default' => '#0d6efd', 'css' => '--bs-primary', 'label' => 'x'],
                'accent'       => ['type' => 'color', 'default' => '#ABC', 'css' => '--kp-accent'],
                'noCss'        => ['type' => 'color', 'default' => '#111111'],
                'footerText'   => ['type' => 'text', 'default' => 'Hello'],
                'cookieNotice' => ['type' => 'bool', 'default' => false],
            ];
        };
    }

    private function child(): ThemeManifest
    {
        return new class () extends ThemeManifest {
            public array $options = [
                'primaryColor' => ['default' => '#6f42c1'],
            ];
        };
    }

    /**
     * @param array<string, mixed> $stored
     */
    private function options(array $stored = []): ThemeOptions
    {
        return new ThemeOptions('child', [$this->child(), $this->parent()], static function (string $key, string $context) use ($stored): mixed {
            return $context === 'theme:child' ? ($stored[$key] ?? null) : null;
        });
    }

    public function testChildRedeclaresOnlyTheDefault(): void
    {
        $definitions = $this->options()->definitions();

        $this->assertSame('color', $definitions['primaryColor']['type']);
        $this->assertSame('--bs-primary', $definitions['primaryColor']['css']);
        $this->assertSame('#6f42c1', $this->options()->get('primaryColor'));
        $this->assertSame('Hello', $this->options()->get('footerText'));
        $this->assertFalse($this->options()->get('cookieNotice'));
        $this->assertNull($this->options()->get('unknown'));
    }

    public function testSavedValuesWinAndAreCast(): void
    {
        $options = $this->options(['footerText' => 'Bye', 'cookieNotice' => '1', 'primaryColor' => '#198754']);

        $this->assertSame('Bye', $options->get('footerText'));
        $this->assertTrue($options->get('cookieNotice'));
        $this->assertSame('#198754', $options->get('primaryColor'));
    }

    public function testCssVariablesOnlyForColourOptionsWithCss(): void
    {
        $css = $this->options()->cssVariables();

        $this->assertSame([
            '--bs-primary'     => '#6f42c1',
            '--bs-primary-rgb' => '111, 66, 193',
            '--kp-accent'      => '#abc',
            '--kp-accent-rgb'  => '170, 187, 204',
        ], $css);
        $this->assertSame(':root { --bs-primary: #6f42c1; --bs-primary-rgb: 111, 66, 193; --kp-accent: #abc; --kp-accent-rgb: 170, 187, 204; }', $this->options()->css());
    }

    public function testAnInvalidSavedColourNeverReachesTheCss(): void
    {
        $options = $this->options(['primaryColor' => 'red;} body{display:none']);

        $this->assertSame('#6f42c1', $options->get('primaryColor'));
        $this->assertStringNotContainsString('display', $options->css());
    }

    public function testContextIsTheThemeSlug(): void
    {
        $this->assertSame('theme:corporate', ThemeOptions::context('corporate'));
    }
}
