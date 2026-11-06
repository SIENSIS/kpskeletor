<?php

declare(strict_types=1);

namespace Modules\Core\Activity;

/**
 * Masks secrets in the data written to the activity log (docs/specification.md, "Activity log"):
 * any key matching `/pass|pwd|secret|token|key|csrf|otp/i`, at any depth, keeps the key and loses
 * its value (`********`; an empty value stays empty so "cleared" is still visible). Long texts are
 * shortened so one entry cannot fill the table.
 */
final class SecretMasker
{
    public const PATTERN = '/pass|pwd|secret|token|key|csrf|otp/i';

    public const MASK = '********';

    /** Characters kept of a long string value. */
    public const MAX_STRING = 1000;

    /** Nesting levels kept (deeper values become "…"). */
    private const MAX_DEPTH = 5;

    public static function isSecret(int|string $key): bool
    {
        return is_string($key) && preg_match(self::PATTERN, $key) === 1;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function mask(array $data, int $depth = 0): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (self::isSecret($key)) {
                $masked[$key] = $value === null || $value === '' ? $value : self::MASK;

                continue;
            }

            $masked[$key] = self::value($value, $depth);
        }

        return $masked;
    }

    private static function value(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $depth >= self::MAX_DEPTH ? '…' : self::mask($value, $depth + 1);
        }

        if (is_object($value)) {
            $value = $value instanceof \Stringable ? (string) $value : (method_exists($value, 'toArray') ? $value->toArray() : get_object_vars($value));

            return self::value($value, $depth);
        }

        if (is_string($value) && mb_strlen($value) > self::MAX_STRING) {
            return mb_substr($value, 0, self::MAX_STRING) . '…';
        }

        return $value === null || is_scalar($value) ? $value : (string) json_encode($value);
    }
}
