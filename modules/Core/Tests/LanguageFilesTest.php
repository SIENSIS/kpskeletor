<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The Core's and the themes' language files have the same keys in ca, es and en.
 *
 * @internal
 */
final class LanguageFilesTest extends CIUnitTestCase
{
    public function testSameKeysInEveryLocale(): void
    {
        $keys = [];

        foreach (['ca', 'es', 'en'] as $locale) {
            $keys[$locale] = $this->flatten(require dirname(__DIR__) . "/Language/{$locale}/Core.php");
        }

        $this->assertSame($keys['en'], $keys['ca']);
        $this->assertSame($keys['en'], $keys['es']);
    }

    public function testThemeLanguageFilesHaveTheSameKeys(): void
    {
        $files = glob(ROOTPATH . 'themes/*/Language/en/*.php') ?: [];
        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $en = $this->flatten(require $file);

            foreach (['ca', 'es'] as $locale) {
                $other = str_replace('/Language/en/', "/Language/{$locale}/", $file);
                $this->assertFileExists($other);
                $this->assertSame($en, $this->flatten(require $other), $other);
            }
        }
    }

    /**
     * @param array<string, mixed> $lines
     *
     * @return list<string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            if (is_array($value)) {
                array_push($keys, ...$this->flatten($value, $prefix . $key . '.'));
            } else {
                $keys[] = $prefix . $key;
            }
        }

        sort($keys);

        return $keys;
    }
}
