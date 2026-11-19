<?php

declare(strict_types=1);

namespace Modules\Core\Content;

use Modules\Core\I18n\Locales;

/**
 * Languages of module content (news, pages, events, menus, projects…) and the lookup of
 * translated texts (docs/i18n.md, "Content languages"; extracted from News in session 20, made to
 * follow the site's locales in session 37).
 *
 * The languages are the site's available locales (`Locales::available()`, setting `Core.locales`)
 * and the required one is the site's default language (`Locales::default()`, setting
 * `Core.defaultLocale`): a language added by configuration reaches every content form, reader and
 * API without touching module code.
 *
 * Content rows keep their texts in a `<table>_translations` table, one row per language
 * (kpcrudy/kpapy `setTranslations()`). A reader gets the texts of the current language, else the
 * default language, else the first available language found; an empty optional field also falls
 * back to the default language. Rows of a language no longer available are kept but neither
 * shown nor edited.
 */
final class ContentTranslations
{
    /**
     * The content languages: available locale => its name in its own language, in order.
     *
     * @return array<string, string>
     */
    public static function locales(): array
    {
        return Locales::names();
    }

    /**
     * The codes of the content languages, in order.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return Locales::available();
    }

    /**
     * The required content language and the first fallback: the site's default language.
     */
    public static function defaultLocale(): string
    {
        return Locales::default();
    }

    public static function isRequired(string $locale): bool
    {
        return $locale === self::defaultLocale();
    }

    /**
     * Options of kpcrudy's `setTranslations()` with the required language and the fallbacks of
     * the site (`required` = the default language, `fallbacks` = it for every other language).
     *
     * @param array<string, mixed> $options the CRUD's own options (types, rules, attrs…)
     *
     * @return array<string, mixed>
     */
    public static function crudOptions(array $options = []): array
    {
        $default = self::defaultLocale();

        return ['required' => [$default], 'fallbacks' => ['*' => [$default]]] + $options;
    }

    /**
     * Options of kpapy's `setTranslations()`: the default language is required.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function apiOptions(array $options = []): array
    {
        return ['required' => [self::defaultLocale()]] + $options;
    }

    /**
     * The source text of a record (for slugs, activity summaries, titles): the default
     * language's, else the first non-empty one of the available languages, in order.
     *
     * @param array<array-key, mixed> $texts locale => text, or locale => [field => text] with `$field`
     */
    public static function defaultText(array $texts, ?string $field = null): string
    {
        $value = static function (mixed $entry) use ($field): string {
            if ($field !== null) {
                $entry = is_array($entry) ? ($entry[$field] ?? '') : '';
            }

            return is_scalar($entry) ? trim((string) $entry) : '';
        };

        $codes = self::codes();
        $first = self::defaultLocale();

        foreach ([$first, ...array_diff($codes, [$first])] as $locale) {
            if (array_key_exists($locale, $texts) && ($text = $value($texts[$locale])) !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * The edited texts plus the stored texts of languages the site no longer offers (they are
     * neither shown nor edited, but kept: a language put back finds them again).
     *
     * @param array<string, mixed> $edited locale => text (the available languages)
     * @param array<string, mixed> $stored locale => text (as saved before)
     *
     * @return array<string, mixed>
     */
    public static function keepUnavailable(array $edited, array $stored): array
    {
        return $edited + array_diff_key($stored, array_flip(self::codes()));
    }

    /**
     * Texts of several rows in a language (default: the request's), else the default language,
     * else the first available language found.
     *
     * @param string       $table  the translations table (e.g. `news_translations`)
     * @param string       $key    its column pointing to the row (e.g. `news_id`)
     * @param list<mixed>  $ids
     * @param list<string> $fields
     *
     * @return array<int, array<string, string>> id => field => text
     */
    public static function texts(string $table, string $key, array $ids, array $fields, ?string $locale = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === [] || $fields === []) {
            return [];
        }

        $locale ??= service('request')->getLocale();
        $rows = db_connect()->table($table)->select($key . ', locale, ' . implode(', ', $fields))->whereIn($key, $ids)->get()->getResultArray();
        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row[$key]][(string) $row['locale']] = $row;
        }

        $texts = [];

        foreach ($byId as $id => $locales) {
            $texts[$id] = self::pick($locales, $fields, $locale);
        }

        return $texts;
    }

    /**
     * The fields of the best language among the rows of one record: the requested language, else
     * the default one, else the first available language found (rows of languages the site no
     * longer offers are ignored).
     *
     * @param array<string, array<string, mixed>> $locales locale => translation row
     * @param list<string>                        $fields
     *
     * @return array<string, string>
     */
    public static function pick(array $locales, array $fields, string $locale): array
    {
        $codes   = self::codes();
        $default = self::defaultLocale();
        $locales = array_intersect_key($locales, array_flip($codes));
        $chosen  = $locales[$locale] ?? $locales[$default] ?? [];

        if ($chosen === []) {
            foreach ($codes as $code) {
                if (isset($locales[$code])) {
                    $chosen = $locales[$code];
                    break;
                }
            }
        }

        $texts = [];

        foreach ($fields as $field) {
            $value = (string) ($chosen[$field] ?? '');

            // A missing optional field falls back too (e.g. a summary written only in the default language).
            if ($value === '' && isset($locales[$default])) {
                $value = (string) ($locales[$default][$field] ?? '');
            }

            $texts[$field] = $value;
        }

        return $texts;
    }
}
