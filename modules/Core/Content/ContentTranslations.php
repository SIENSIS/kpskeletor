<?php

declare(strict_types=1);

namespace Modules\Core\Content;

/**
 * Languages of module content (news, pages, events…) and the lookup of translated texts with the
 * Catalan fallback (docs/specification.md, "Languages"; extracted from News in session 20).
 *
 * Content rows keep their texts in a `<table>_translations` table, one row per language
 * (kpcrudy/kpapy `setTranslations()`). A reader gets the texts of the current language, else
 * Catalan, else the first language found; an empty optional field also falls back to Catalan.
 */
final class ContentTranslations
{
    /** Languages of the content: Catalan (required, the default), Spanish, English. */
    public const LOCALES = ['ca' => 'Català', 'es' => 'Español', 'en' => 'English'];

    /** Language used when a row has no text in the current one (the default content language). */
    public const FALLBACK_LOCALE = 'ca';

    /**
     * Texts of several rows in a language (default: the request's), else Catalan, else the first
     * language found.
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
     * The fields of the best language among the rows of one record.
     *
     * @param array<string, array<string, mixed>> $locales locale => translation row
     * @param list<string>                        $fields
     *
     * @return array<string, string>
     */
    public static function pick(array $locales, array $fields, string $locale): array
    {
        $chosen = $locales[$locale] ?? $locales[self::FALLBACK_LOCALE] ?? (reset($locales) ?: []);
        $texts  = [];

        foreach ($fields as $field) {
            $value = (string) ($chosen[$field] ?? '');

            // A missing optional field falls back too (e.g. a summary written only in Catalan).
            if ($value === '' && isset($locales[self::FALLBACK_LOCALE])) {
                $value = (string) ($locales[self::FALLBACK_LOCALE][$field] ?? '');
            }

            $texts[$field] = $value;
        }

        return $texts;
    }
}
