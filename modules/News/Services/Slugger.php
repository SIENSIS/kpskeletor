<?php

declare(strict_types=1);

namespace Modules\News\Services;

/**
 * Slugs of posts and categories: lower case ASCII words joined with `-` (accents transliterated),
 * unique in their table (`-2`, `-3`… on a clash), at most 180 characters.
 */
final class Slugger
{
    public static function slug(string $text): string
    {
        helper('text');

        // Elided articles (l’escola, d'estiu) keep their words apart.
        $text  = str_replace(['’', "'", '`', '´'], ' ', $text);
        $ascii = function_exists('transliterator_transliterate')
            ? (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text)
            : convert_accented_characters($text);
        $slug  = url_title(strtolower($ascii), '-', true);
        $slug  = trim(substr($slug, 0, 180), '-');

        return $slug !== '' ? $slug : 'post';
    }

    /**
     * A slug not used by another row of the table (`$exceptId`: the row being edited).
     */
    public static function unique(string $table, string $text, ?int $exceptId = null): string
    {
        $base = self::slug($text);
        $slug = $base;

        for ($i = 2; self::taken($table, $slug, $exceptId); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private static function taken(string $table, string $slug, ?int $exceptId): bool
    {
        $builder = db_connect()->table($table)->where('slug', $slug);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }
}
