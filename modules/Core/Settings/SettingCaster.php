<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * Casts a raw value (a manifest default, a form field, a stored string) to a setting's type.
 *
 * `list` is a list of strings: from an array, a JSON array or text with one entry per line (or
 * separated by commas); entries are trimmed, empty ones and duplicates dropped.
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
            'list'  => self::list($value),
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
