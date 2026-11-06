<?php

declare(strict_types=1);

namespace Modules\Core\Log;

use CodeIgniter\Log\Logger;

/**
 * CodeIgniter's logger with every value interpolated into a message made single-line: line
 * breaks and other control characters are written as `\n`, `\r`, `\t` or `\xNN`, so a value
 * from user input (an email typed in the login form, a path) cannot start a forged log line
 * (docs/specification.md, "Activity log": "the file logs escape line breaks").
 *
 * The message itself is code; only the `{placeholders}` values are escaped.
 */
class SafeLogger extends Logger
{
    public static function escape(string $value): string
    {
        return (string) preg_replace_callback(
            '/[\x00-\x1F\x7F\x{2028}\x{2029}]/u',
            static fn (array $m): string => match ($m[0]) {
                "\n"    => '\n',
                "\r"    => '\r',
                "\t"    => '\t',
                default => strlen($m[0]) === 1 ? sprintf('\x%02X', ord($m[0])) : sprintf('\u%04X', mb_ord($m[0])),
            },
            $value,
        );
    }

    protected function stringifyContextValue(int|string $key, mixed $value): string
    {
        return self::escape(parent::stringifyContextValue($key, $value));
    }
}
