<?php

declare(strict_types=1);

namespace Modules\Events\Ical;

/**
 * Text escaping and line folding of RFC 5545 (sections 3.1 and 3.3.11).
 */
final class Folding
{
    /** Longest line in octets, without the CRLF. */
    public const MAX_OCTETS = 75;

    /**
     * Splits a content line into lines of at most 75 octets (continuations start with one space),
     * never inside a UTF-8 character.
     */
    public static function fold(string $line): string
    {
        if (strlen($line) <= self::MAX_OCTETS) {
            return $line;
        }

        $parts   = [];
        $current = '';
        $limit   = self::MAX_OCTETS;

        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $parts[] = $current;
                $current = '';
                $limit   = self::MAX_OCTETS - 1; // the leading space counts
            }

            $current .= $char;
        }

        $parts[] = $current;

        return implode("\r\n ", $parts);
    }

    /**
     * Escapes a TEXT value: backslash, semicolon, comma and new lines; other control characters
     * are removed.
     */
    public static function text(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $value);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $value);
    }

    /**
     * A parameter value (quoted when it contains `:`, `;` or `,`; double quotes are not allowed).
     */
    public static function param(string $value): string
    {
        $value = str_replace(['"', "\r", "\n"], ['\'', ' ', ' '], $value);

        return preg_match('/[:;,]/', $value) === 1 ? '"' . $value . '"' : $value;
    }
}
