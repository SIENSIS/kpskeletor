<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * A text of the generated module in the three shipped languages (`ca`, `es`, `en`). A missing
 * language takes the English text, then any given one, then the fallback (usually the
 * humanised name), so a definition may give only one of them.
 */
final class Texts
{
    public const LOCALES = ['ca', 'es', 'en'];

    /**
     * @param array<string, string> $texts locale => text
     */
    private function __construct(public readonly array $texts)
    {
    }

    public static function from(mixed $value, string $fallback): self
    {
        $given = [];

        if (is_string($value) && trim($value) !== '') {
            $given = array_fill_keys(self::LOCALES, trim($value));
        } elseif (is_array($value)) {
            foreach (self::LOCALES as $locale) {
                if (is_string($value[$locale] ?? null) && trim($value[$locale]) !== '') {
                    $given[$locale] = trim($value[$locale]);
                }
            }
        }

        $default = $given['en'] ?? ($given === [] ? $fallback : reset($given));
        $texts   = [];

        foreach (self::LOCALES as $locale) {
            $texts[$locale] = $given[$locale] ?? $default;
        }

        return new self($texts);
    }

    public function get(string $locale): string
    {
        return $this->texts[$locale] ?? $this->texts['en'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->texts;
    }

    /**
     * `book_title` → `Book title`.
     */
    public static function humanise(string $name): string
    {
        $text = trim((string) preg_replace('/[_\-\s]+/', ' ', $name));

        return $text === '' ? $name : ucfirst($text);
    }
}
