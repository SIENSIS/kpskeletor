<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * Casts a raw value (a manifest default, a form field, a stored string) to a setting's type.
 *
 * `list` is a list of strings: from an array, a JSON array or text with one entry per line (or
 * separated by commas); entries are trimmed, empty ones and duplicates dropped.
 *
 * `multiselect` (checkboxes) is stored like `list`: the chosen values.
 *
 * `translated` is a map locale => text (from an array or a JSON object): keys must look like a
 * locale (`ca`, `pt-BR`), values are strings (line breaks normalised); empty texts are dropped and
 * the keys sorted, so two equal maps compare equal (TranslatedText).
 */
final class SettingCaster
{
    public static function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int'   => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'bool'  => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json'  => self::json($value),
            'list', 'multiselect' => self::list($value),
            'translated' => self::translated($value),
            default => is_scalar($value) ? (string) $value : '',
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private static function translated(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [];
        }

        $map = [];

        foreach (is_array($value) ? $value : [] as $locale => $text) {
            if (! is_string($locale) || preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $locale) !== 1 || ! is_scalar($text)) {
                continue;
            }

            $text = trim(str_replace("\r\n", "\n", (string) $text));

            if ($text !== '') {
                $map[$locale] = $text;
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : preg_split('/[\r\n,]+/', $value);
        }

        $list = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $item = is_scalar($item) ? trim((string) $item) : '';

            if ($item !== '' && ! in_array($item, $list, true)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
