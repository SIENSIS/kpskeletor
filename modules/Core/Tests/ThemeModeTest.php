<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Modules\Core\Frontend\ThemeMode;
use Modules\Core\Theme\ThemeManifest;
use Modules\Core\Theme\ThemeOptions;

/**
 * Light/dark/auto mode: forced by the theme, the user's choice, the cookie, the default; the
 * <html> attributes (no flash), the switch visibility, dark-mode option values and the
 * remembered sidebar and cookie notice.
 *
 * @internal
 */
final class ThemeModeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function tearDown(): void
    {
        service('superglobals')->unsetCookie('kp_theme')->unsetCookie('kp_sidebar')->unsetCookie('kp_cookies');
        service('theme')->reset();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $values option values
     */
    private function options(array $values = []): ThemeOptions
    {
        $manifest = new class () extends ThemeManifest {
            public array $options = [
                'primaryColor'     => ['type' => 'color', 'default' => '#0d6efd', 'css' => '--bs-primary'],
                'primaryColorDark' => ['type' => 'color', 'default' => '', 'css' => '--bs-primary', 'mode' => 'dark'],
                'defaultMode'      => ['type' => 'select', 'default' => 'auto'],
                'forceMode'        => ['type' => 'select', 'default' => ''],
                'modeSwitch'       => ['type' => 'bool', 'default' => true],
            ];
        };

        return new ThemeOptions('t', [$manifest], static fn (string $key): mixed => $values[$key] ?? null);
    }

    /**
     * @param array<string, mixed> $values option values
     */
    private function mode(array $values = [], ?string $cookie = null, ?string $preference = null): ThemeMode
    {
        return new ThemeMode($this->options($values), static fn (): ?string => $cookie, static fn (): ?string => $preference);
    }

    public function testOrderOfTheChoices(): void
    {
        $this->assertSame('auto', $this->mode()->current());
        $this->assertSame('light', $this->mode(['defaultMode' => 'light'])->current());
        $this->assertSame('dark', $this->mode(['defaultMode' => 'light'], 'dark')->current(), 'cookie over default');
        $this->assertSame('light', $this->mode([], 'dark', 'light')->current(), 'profile over cookie');
        $this->assertSame('auto', $this->mode([], 'purple')->current(), 'invalid cookie ignored');
        $this->assertSame('dark', $this->mode(['forceMode' => 'dark'], 'light', 'light')->current(), 'forced by the theme');
    }

    public function testAttributesAndSwitch(): void
    {
        $this->assertSame(' data-theme-mode="auto"', $this->mode()->attributes());
        $this->assertSame(' data-bs-theme="dark" data-theme-mode="dark"', $this->mode([], 'dark')->attributes());
        $this->assertSame(' data-bs-theme="light" data-theme-mode="light"', $this->mode([], 'dark')->attributes('light'));
        $this->assertSame(' data-theme-mode="auto"', $this->mode()->attributes('"><script>'));

        $this->assertTrue($this->mode()->switchVisible());
        $this->assertFalse($this->mode(['modeSwitch' => false])->switchVisible());
        $this->assertFalse($this->mode(['forceMode' => 'light'])->switchVisible(), 'a forced mode hides the switch');
    }

    public function testDarkValuesOfOptions(): void
    {
        $this->assertSame(':root { --bs-primary: #0d6efd; --bs-primary-rgb: 13, 110, 253; --kp-primary-light-rgb: 122, 175, 254; }', $this->options()->css(), 'empty dark value keeps the light one');
        $this->assertSame(
            ':root { --bs-primary: #0d6efd; --bs-primary-rgb: 13, 110, 253; --kp-primary-light-rgb: 122, 175, 254; } [data-bs-theme=dark] { --bs-primary: #6ea8fe; --bs-primary-rgb: 110, 168, 254; --kp-primary-light-rgb: 175, 207, 254; }',
            $this->options(['primaryColorDark' => '#6ea8fe'])->css(),
        );
        $this->assertStringNotContainsString('dark', $this->options(['primaryColorDark' => 'red;}body{'])->css());
    }

    private function cookie(string $name, string $value): void
    {
        service('superglobals')->setCookie($name, $value);
        Services::resetSingle('request');
        service('theme')->reset();
    }

    public function testServerPrintsTheRememberedChoices(): void
    {
        $this->cookie('kp_theme', 'dark');
        $html = (string) $this->get('/')->response()->getBody();
        $this->assertMatchesRegularExpression('/<html lang="[a-z]+" data-bs-theme="dark" data-theme-mode="dark">/', $html);
        $this->assertMatchesRegularExpression('/data-theme-mode-value="dark" aria-pressed="true"/', $html);

        $this->cookie('kp_theme', 'nonsense');
        $html = (string) $this->get('/')->response()->getBody();
        $this->assertMatchesRegularExpression('/<html lang="[a-z]+" data-theme-mode="auto">/', $html);

        foreach (['collapsed' => 'sidebar-collapsed', 'expanded' => 'sidebar-expanded', 'x" onload="' => ''] as $value => $class) {
            $this->cookie('kp_sidebar', $value);
            $this->assertSame($class, theme_sidebar_class());
        }
    }

    public function testCookieNoticeIsRememberedOnceClosed(): void
    {
        $render = static fn (): string => theme()->render('partials/cookie_notice', [], ['saveData' => false]);
        // Turn the theme option on without the settings store.
        $options = new \ReflectionProperty(\Modules\Core\Theme\ThemeManager::class, 'options');
        $manager = theme();
        $manager->reset();
        $options->setValue($manager, ['public' => new ThemeOptions('default', $manager->manifests('public'), static fn (string $key): mixed => $key === 'cookieNotice' ? true : null)]);
        $this->assertStringContainsString('data-cookie-notice', $render());

        service('superglobals')->setCookie('kp_cookies', '1');
        Services::resetSingle('request');
        $this->assertStringNotContainsString('data-cookie-notice', $render());
    }
}
