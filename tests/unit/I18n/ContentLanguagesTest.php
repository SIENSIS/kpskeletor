<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * No module or theme code names a content language literally (session 37, docs/i18n.md
 * "Content languages"): the languages offered and the required one come from the site's locales
 * (`Modules\Core\Content\ContentTranslations`, `Modules\Core\I18n\Locales`), so a language added
 * by configuration reaches every form, reader and API.
 *
 * Allowed: language files, migrations and seeders (`Database/`, released data and demo data),
 * tests, configuration (`Config/`: e.g. the Core manifest's language options), `Locales` itself
 * and the language-line chain of `Modules\Core\I18n\Language` (English is always its last resort).
 *
 * @internal
 */
final class ContentLanguagesTest extends CIUnitTestCase
{
    /** Codes of the shipped languages written as a PHP string: 'ca', "es", 'en-GB'… */
    private const PATTERN = '/([\'"])(ca|es|en)(-[A-Za-z]{2})?\1/';

    /** Paths (relative to the root) allowed to name a language. */
    private const ALLOWED = [
        'modules/Core/I18n/Locales.php',
        'modules/Core/I18n/Language.php',
    ];

    public function testNoModuleCodeNamesAContentLanguage(): void
    {
        $found   = [];
        $scanned = 0;

        foreach ($this->files() as $file) {
            $relative = substr($file, strlen(ROOTPATH));

            if (in_array($relative, self::ALLOWED, true) || preg_match('#/(Language|Database|Tests|Config)/#', $relative) === 1) {
                continue;
            }

            $scanned++;

            foreach (file($file) ?: [] as $number => $line) {
                if (preg_match(self::PATTERN, $line) === 1 && ! $this->isComment($line)) {
                    $found[] = $relative . ':' . ($number + 1) . ': ' . trim($line);
                }
            }
        }

        $this->assertGreaterThan(100, $scanned, 'The scan reads the modules.');
        $this->assertSame([], $found, 'Use ContentTranslations / Locales instead of a literal language code.');
    }

    public function testTheRemovedConstantsAreNotUsed(): void
    {
        $found = [];

        foreach ($this->files() as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/ContentTranslations::(LOCALES|FALLBACK_LOCALE)|NewsRepository::FALLBACK_LOCALE|Posts::LOCALES|TranslatedText::FALLBACK_LOCALE/', $source) === 1) {
                $found[] = substr($file, strlen(ROOTPATH));
            }
        }

        $this->assertSame([], $found);
    }

    /**
     * Every PHP file of the modules and themes.
     *
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach ([ROOTPATH . 'modules', ROOTPATH . 'themes'] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function isComment(string $line): bool
    {
        return preg_match('#^\s*(\*|//|/\*)#', $line) === 1;
    }
}
