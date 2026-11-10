<?php

declare(strict_types=1);

namespace Modules\Core\Text;

/**
 * Slugs for public URLs (news, categories, pages, events…): lower case ASCII words joined with
 * `-` (accents transliterated, elided articles split), at most 180 characters, unique in their
 * table (`-2`, `-3`… on a clash), optionally only among the rows of a scope (e.g. the same parent
 * page and language).
 */
final class Slugger
{
    /**
     * Validation rules of a slug typed by hand (empty = generated from the title).
     *
     * @return list<string>
     */
    public static function rules(int $maxLength = 180): array
    {
        return ['permit_empty', 'max_length[' . $maxLength . ']', 'regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'];
    }

    /**
     * @param string $fallback Slug when the text has no letters or digits
     */
    public static function slug(string $text, string $fallback = 'item'): string
    {
        helper('text');

        // Elided articles (l’escola, d'estiu) keep their words apart.
        $text  = str_replace(['’', "'", '`', '´'], ' ', $text);
        $ascii = function_exists('transliterator_transliterate')
            ? (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text)
            : convert_accented_characters($text);
        $slug  = url_title(strtolower($ascii), '-', true);
        $slug  = trim(substr($slug, 0, 180), '-');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * A slug not used by another row of the table.
     *
     * @param int|null             $exceptId The row being edited
     * @param array<string, mixed> $scope    Only rows with these column values clash (e.g. ['parent_id' => 3])
     */
    public static function unique(string $table, string $text, ?int $exceptId = null, array $scope = [], string $column = 'slug', string $fallback = 'item'): string
    {
        $base = self::slug($text, $fallback);
        $slug = $base;

        for ($i = 2; self::taken($table, $column, $slug, $exceptId, $scope); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $scope
     */
    private static function taken(string $table, string $column, string $slug, ?int $exceptId, array $scope): bool
    {
        $builder = db_connect()->table($table)->where($column, $slug);

        foreach ($scope as $key => $value) {
            $builder->where($key, $value);
        }

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }
}
