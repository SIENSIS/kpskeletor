<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\I18n\Language;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Notifications\NotificationType;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Widgets\Widget;
use ReflectionClass;

/**
 * The CI check of the languages (docs/i18n.md): every language file of the modules, the themes
 * and the application exists in ca, es and en with exactly the same keys and no empty text; the
 * Catalan copies of CodeIgniter's and Shield's files have their keys; every literal language key
 * used in the code and every key of the manifests exists in the three languages (no untranslated
 * key on screen).
 *
 * @internal
 */
final class LanguageKeysTest extends CIUnitTestCase
{
    private const LOCALES = ['ca', 'es', 'en'];

    protected function setUp(): void
    {
        parent::setUp();

        // Theme language files load once their theme is read (ThemeRegistry registers its namespace).
        $themes = new \Modules\Core\Theme\ThemeRegistry();

        foreach ($themes->slugs() as $slug) {
            $themes->get($slug);
        }
    }

    /**
     * CodeIgniter files shown to users; `codeigniter4/translations` has Spanish, the application
     * ships Catalan (the others follow the fallback chain: Catalan → Spanish).
     */
    private const SYSTEM_FILES = ['Validation', 'Pager', 'Errors', 'Security', 'Number', 'Time'];

    public function testEveryLanguageFileHasTheSameKeysInTheThreeLanguages(): void
    {
        $files = array_merge(
            glob(ROOTPATH . 'modules/*/Language/en/*.php') ?: [],
            glob(ROOTPATH . 'themes/*/Language/en/*.php') ?: [],
        );
        $this->assertGreaterThan(15, count($files));

        foreach ($files as $file) {
            $en = $this->flatten(require $file);

            foreach (['ca', 'es'] as $locale) {
                $other = str_replace('/Language/en/', "/Language/{$locale}/", $file);
                $this->assertFileExists($other);
                $this->assertSame(array_keys($en), array_keys($this->flatten(require $other)), $this->relative($other));
            }
        }

        // No file in ca or es without its English counterpart.
        foreach (['ca', 'es'] as $locale) {
            foreach (array_merge(glob(ROOTPATH . "modules/*/Language/{$locale}/*.php") ?: [], glob(ROOTPATH . "themes/*/Language/{$locale}/*.php") ?: []) as $file) {
                $this->assertFileExists(str_replace("/Language/{$locale}/", '/Language/en/', $file));
            }
        }
    }

    public function testNoTextIsEmpty(): void
    {
        foreach (array_merge(glob(ROOTPATH . 'modules/*/Language/*/*.php') ?: [], glob(ROOTPATH . 'themes/*/Language/*/*.php') ?: [], glob(ROOTPATH . 'app/Language/*/*.php') ?: []) as $file) {
            foreach ($this->flatten(require $file) as $key => $text) {
                $this->assertNotSame('', trim($text), $this->relative($file) . ': ' . $key);
            }
        }
    }

    public function testCatalanCopiesOfTheSystemFilesHaveTheirKeys(): void
    {
        foreach (self::SYSTEM_FILES as $name) {
            $ca = ROOTPATH . "app/Language/ca/{$name}.php";
            $this->assertFileExists($ca);
            $this->assertSame(array_keys($this->flatten(require SYSTEMPATH . "Language/en/{$name}.php")), array_keys($this->flatten(require $ca)), $name);
            $this->assertFileExists(ROOTPATH . "vendor/codeigniter4/translations/Language/es/{$name}.php");
        }

        // Shield's texts: the Auth module defines some, the application the rest in Catalan.
        $shield = array_keys(require ROOTPATH . 'vendor/codeigniter4/shield/src/Language/en/Auth.php');
        $module = array_keys(require ROOTPATH . 'modules/Auth/Language/ca/Auth.php');
        $this->assertSame(array_values(array_diff($shield, $module)), array_keys(require ROOTPATH . 'app/Language/ca/Auth.php'));

        // Only these Catalan copies (and no English overrides) live in app/Language.
        $this->assertSame([], glob(ROOTPATH . 'app/Language/{en,es}/*.php', GLOB_BRACE) ?: []);
    }

    public function testEveryLiteralKeyUsedInTheCodeExists(): void
    {
        $language = new Language('en');
        $files    = $this->languageFiles();
        $missing  = [];

        foreach ($this->sourceFiles() as $file) {
            $code = (string) file_get_contents($file);

            // A literal key passed whole (not a prefix completed with `.`), outside comments.
            preg_match_all("/^(?!\\s*(?:\\*|\\/\\/)).*?\\blang\\(\\s*'([A-Z][A-Za-z]+\\.[A-Za-z0-9_.\\-]*[A-Za-z0-9])'\\s*[,)]/m", $code, $m);

            foreach (array_unique($m[1]) as $key) {
                if (! isset($files[explode('.', $key, 2)[0]])) {
                    continue; // not one of the application's files (e.g. a library's)
                }

                foreach (self::LOCALES as $locale) {
                    if (! $language->has($key, $locale)) {
                        $missing[] = "{$locale}: {$key} (" . $this->relative($file) . ')';
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function testEveryKeyOfTheManifestsExists(): void
    {
        $language = new Language('en');
        $files    = $this->languageFiles();
        $missing  = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            $manifest = $module->manifest;

            if ($manifest === null) {
                continue;
            }

            $strings = [];
            array_walk_recursive($manifest->resources, static function ($v) use (&$strings): void {
                $strings[] = $v;
            });
            array_walk_recursive($manifest->menu, static function ($v) use (&$strings): void {
                $strings[] = $v;
            });
            array_walk_recursive($manifest->settings, static function ($v) use (&$strings): void {
                $strings[] = $v;
            });
            array_walk_recursive($manifest->calendars, static function ($v) use (&$strings): void {
                $strings[] = $v;
            });

            // A module with settings has its own tab, named `<Module>.module.name` (Settings).
            if ($manifest->settings !== [] && $name !== 'Core') {
                $strings[] = $name . '.module.name';
            }

            foreach ($strings as $value) {
                if (! is_string($value) || preg_match('/^([A-Z][A-Za-z]+)\.[A-Za-z0-9_.]+$/', $value, $m) !== 1 || ! isset($files[$m[1]])) {
                    continue;
                }

                foreach (self::LOCALES as $locale) {
                    if (! $language->has($value, $locale)) {
                        $missing[] = "{$name} {$locale}: {$value}";
                    }
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function testWidgetsNotificationsAndTasksHaveTitlesInTheThreeLanguages(): void
    {
        $language = service('language');
        $previous = $language->getLocale();
        $looksLikeKey = static fn (string $text): bool => preg_match('/^[A-Z][A-Za-z]+\.[A-Za-z0-9_.]+$/', $text) === 1;

        try {
            foreach ((new ModuleDiscovery())->discover() as $name => $module) {
                $manifest = $module->manifest;

                if ($manifest === null) {
                    continue;
                }

                foreach (self::LOCALES as $locale) {
                    $language->setLocale($locale);

                    foreach ($manifest->notifications as $class) {
                        if (is_subclass_of($class, NotificationType::class)) {
                            $this->assertFalse($looksLikeKey($class::title()), "{$class} ({$locale}): " . $class::title());
                        }
                    }

                    foreach ([...$manifest->widgets, ...$manifest->tasks] as $class) {
                        if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()) {
                            continue;
                        }

                        if (is_subclass_of($class, Widget::class) || is_subclass_of($class, TaskInterface::class)) {
                            $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
                            $text   = $object instanceof Widget ? $object->title() : $object->name();
                            $this->assertFalse($looksLikeKey($text), "{$class} ({$locale}): {$text}");
                        }
                    }
                }
            }
        } finally {
            $language->setLocale($previous);
        }
    }

    /**
     * Names of the application's language files (`Core`, `News`…, CodeIgniter's and Shield's
     * are not checked here).
     *
     * @return array<string, true>
     */
    private function languageFiles(): array
    {
        $names = [];

        foreach (array_merge(glob(ROOTPATH . 'modules/*/Language/en/*.php') ?: [], glob(ROOTPATH . 'themes/*/Language/en/*.php') ?: []) as $file) {
            $names[basename($file, '.php')] = true;
        }

        unset($names['Auth']); // Shield's Auth keys are merged into it: checked above

        return $names + ['Auth' => true];
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach ([ROOTPATH . 'modules', ROOTPATH . 'themes', APPPATH] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                $path = $file->getPathname();

                if (str_ends_with($path, '.php') && ! str_contains($path, '/Tests/') && ! str_contains($path, '/Language/')) {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param array<array-key, mixed> $lines
     *
     * @return array<string, string> key (dotted) => text, sorted by key
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            if (is_array($value)) {
                $keys += $this->flatten($value, $prefix . $key . '.');
            } else {
                $keys[$prefix . $key] = is_scalar($value) ? (string) $value : '';
            }
        }

        ksort($keys);

        return $keys;
    }

    private function relative(string $path): string
    {
        return str_replace(ROOTPATH, '', $path);
    }
}
