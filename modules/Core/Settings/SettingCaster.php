<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * Casts a raw value (a manifest default, a form field, a stored string) to a setting's type.
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
}
