<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use CodeIgniter\Language\Language as BaseLanguage;
use Config\App;

/**
 * The application's `language` service (app/Config/Services.php), used by every module and by
 * kpcrudy/kpapy (they call lang()): a line missing in the requested locale is looked up along
 * the fallback chain requested → locale without region (`ca-ES` → `ca`) → `Config\App::$localeFallbacks`
 * (`es`, `en`) → `en`, instead of CodeIgniter's requested → `en` (docs/i18n.md).
 */
class Language extends BaseLanguage
{
    /**
     * The locales searched for a line, in order (no duplicates).
     *
     * @return list<string>
     */
    public function chain(?string $locale = null): array
    {
        $locale ??= $this->locale;
        $chain  = [$locale];

        if (str_contains($locale, '-')) {
            $chain[] = explode('-', $locale, 2)[0];
        }

        foreach ([...config(App::class)->localeFallbacks, 'en'] as $fallback) {
            if ($fallback !== '') {
                $chain[] = $fallback;
            }
        }

        return array_values(array_unique($chain));
    }

    /**
     * @param array<array-key, mixed> $args
     *
     * @return list<string>|string
     */
    public function getLine(string $line, array $args = [])
    {
        if (! str_contains($line, '.')) {
            return $this->formatMessage($line, $args);
        }

        $output = null;

        foreach ($this->chain() as $locale) {
            [$file, $parsedLine] = $this->parseLine($line, $locale);
            $output              = $this->getTranslationOutput($locale, $file, $parsedLine);

            if ($output !== null) {
                break;
            }
        }

        return $this->formatMessage($output ?? $line, $args);
    }

    /**
     * Whether a line exists in a locale itself (no fallback); used by the key checks.
     */
    public function has(string $line, ?string $locale = null): bool
    {
        if (! str_contains($line, '.')) {
            return false;
        }

        $locale ??= $this->locale;
        [$file, $parsedLine] = $this->parseLine($line, $locale);

        return $this->getTranslationOutput($locale, $file, $parsedLine) !== null;
    }
}
