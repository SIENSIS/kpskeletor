<?php

declare(strict_types=1);

namespace Modules\Events\Tests\Support;

/**
 * A strict RFC 5545 checker for the tests (no external validator is installed): line endings,
 * folding (75 octets), component nesting, required properties, value formats of the properties
 * the calendar writes, time zone references and unique UIDs. `validate()` returns the problems.
 */
final class IcalValidator
{
    private const DATE_TIME = '/^\d{8}T\d{6}Z?$/';

    private const DATE = '/^\d{8}$/';

    /**
     * @return list<string>
     */
    public static function validate(string $ics): array
    {
        $errors = [];

        if (! str_ends_with($ics, "\r\n")) {
            $errors[] = 'The document must end with CRLF.';
        }

        if (preg_match("/(?<!\r)\n|\r(?!\n)/", $ics) === 1) {
            $errors[] = 'Bare LF or CR found: lines must end with CRLF.';
        }

        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $n => $line) {
            if (strlen($line) > 75) {
                $errors[] = 'Line ' . ($n + 1) . ' is longer than 75 octets.';
            }

            if (! mb_check_encoding($line, 'UTF-8')) {
                $errors[] = 'Line ' . ($n + 1) . ' is not valid UTF-8 (a fold split a character?).';
            }
        }

        $lines = self::unfold($ics);
        $stack = [];
        $tzids = [];
        $used  = [];
        $uids  = [];
        $props = [];

        foreach ($lines as $n => $line) {
            if (preg_match('/^([A-Za-z0-9-]+)((?:;[A-Za-z0-9-]+=(?:"[^"]*"|[^";:,]*)(?:,(?:"[^"]*"|[^";:,]*))*)*):(.*)$/s', $line, $m) !== 1) {
                $errors[] = 'Unparsable content line ' . ($n + 1) . ': ' . $line;

                continue;
            }

            [$name, $params, $value] = [strtoupper($m[1]), $m[2], $m[3]];

            if ($name === 'BEGIN') {
                $stack[] = $value;
                $props[] = [];

                continue;
            }

            if ($name === 'END') {
                $open = array_pop($stack);
                $have = array_pop($props) ?? [];

                if ($open !== $value) {
                    $errors[] = "END:{$value} closes " . ($open ?? 'nothing') . '.';
                }

                $errors = [...$errors, ...self::required((string) $value, $have, $stack)];

                if ($value === 'VEVENT') {
                    $uid = $have['UID'][0] ?? '';

                    if (isset($uids[$uid])) {
                        $errors[] = "Duplicate UID {$uid}.";
                    }

                    $uids[$uid] = true;
                    $start      = $have['DTSTART'][0] ?? '';
                    $end        = $have['DTEND'][0] ?? '';

                    if ($end !== '' && $start !== '' && strlen($start) === strlen($end) && strcmp($end, $start) < 0) {
                        $errors[] = "DTEND before DTSTART in {$uid}.";
                    }
                }

                continue;
            }

            if ($stack === []) {
                $errors[] = "Property {$name} outside VCALENDAR.";

                continue;
            }

            $props[count($props) - 1][$name][] = $value;

            if (preg_match('/;TZID=("?)([^";:]+)\1/', $params, $tz) === 1) {
                $used[$tz[2]] = true;
            }

            if ($name === 'TZID' && end($stack) === 'VTIMEZONE') {
                $tzids[$value] = true;
            }

            $errors = [...$errors, ...self::format($name, $params, $value)];
        }

        if ($stack !== []) {
            $errors[] = 'Unclosed components: ' . implode(', ', $stack);
        }

        foreach (array_keys($used) as $tzid) {
            if (! isset($tzids[$tzid])) {
                $errors[] = "TZID {$tzid} is used but has no VTIMEZONE.";
            }
        }

        return $errors;
    }

    /**
     * The logical (unfolded) lines.
     *
     * @return list<string>
     */
    public static function unfold(string $ics): array
    {
        return explode("\r\n", rtrim(str_replace("\r\n ", '', $ics), "\r\n"));
    }

    /**
     * The VEVENTs as property => list of "params:value" strings.
     *
     * @return list<array<string, list<string>>>
     */
    public static function events(string $ics): array
    {
        $events  = [];
        $current = null;

        foreach (self::unfold($ics) as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
            } elseif ($line === 'END:VEVENT' && $current !== null) {
                $events[] = $current;
                $current  = null;
            } elseif ($current !== null && preg_match('/^([A-Z-]+)([;:].*)$/', $line, $m) === 1) {
                $current[$m[1]][] = ltrim($m[2], ':;');
            }
        }

        return $events;
    }

    /**
     * @param array<string, list<string>> $have
     * @param list<string>                $stack
     *
     * @return list<string>
     */
    private static function required(string $component, array $have, array $stack): array
    {
        $needed = match ($component) {
            'VCALENDAR' => ['VERSION', 'PRODID'],
            'VEVENT'    => ['UID', 'DTSTAMP', 'DTSTART'],
            'VTIMEZONE' => ['TZID'],
            'STANDARD', 'DAYLIGHT' => ['DTSTART', 'TZOFFSETFROM', 'TZOFFSETTO'],
            'VALARM'    => ['ACTION', 'TRIGGER', 'DESCRIPTION'],
            default     => [],
        };
        $errors = [];

        foreach ($needed as $property) {
            if (! isset($have[$property])) {
                $errors[] = "{$component} without {$property}.";
            }
        }

        foreach (['UID', 'DTSTAMP', 'DTSTART', 'DTEND', 'SEQUENCE', 'SUMMARY', 'STATUS'] as $once) {
            if (count($have[$once] ?? []) > 1) {
                $errors[] = "{$component} has {$once} more than once.";
            }
        }

        if ($component === 'VCALENDAR' && ($have['VERSION'][0] ?? '') !== '2.0') {
            $errors[] = 'VERSION must be 2.0.';
        }

        if ($component === 'VALARM' && $stack !== [] && end($stack) !== 'VEVENT') {
            $errors[] = 'VALARM outside VEVENT.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function format(string $name, string $params, string $value): array
    {
        $date = str_contains($params, 'VALUE=DATE') && ! str_contains($params, 'VALUE=DATE-TIME');

        $ok = match ($name) {
            'DTSTAMP', 'LAST-MODIFIED', 'CREATED' => preg_match('/^\d{8}T\d{6}Z$/', $value) === 1,
            'DTSTART', 'DTEND' => preg_match($date ? self::DATE : self::DATE_TIME, $value) === 1,
            'EXDATE' => array_reduce(explode(',', $value), static fn (bool $carry, string $v): bool => $carry && preg_match($date ? self::DATE : self::DATE_TIME, $v) === 1, true),
            'SEQUENCE' => preg_match('/^\d+$/', $value) === 1,
            'STATUS' => in_array($value, ['TENTATIVE', 'CONFIRMED', 'CANCELLED'], true),
            'RRULE' => preg_match('/^FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)(;(INTERVAL=\d+|COUNT=\d+|UNTIL=\d{8}(T\d{6}Z)?|BYDAY=((MO|TU|WE|TH|FR|SA|SU),?)+))*$/', $value) === 1
                && ! (str_contains($value, 'COUNT=') && str_contains($value, 'UNTIL=')),
            'TZOFFSETFROM', 'TZOFFSETTO' => preg_match('/^[+-]\d{4}$/', $value) === 1,
            'TRIGGER' => preg_match('/^-?P(T\d+[HMS]|\d+[DW])$/', $value) === 1,
            'REFRESH-INTERVAL', 'X-PUBLISHED-TTL' => preg_match('/^P(T?\d+[WDHMS])+$/', $value) === 1,
            'URL' => preg_match('#^https?://\S+$#', $value) === 1,
            'SUMMARY', 'DESCRIPTION', 'LOCATION', 'CATEGORIES', 'X-WR-CALNAME' => preg_match('/(?<!\\\\)[;,]|(?<!\\\\)\\\\(?![\\\;,nN])/', $value) !== 1,
            default => true,
        };

        return $ok ? [] : ["Invalid {$name}{$params} value: {$value}"];
    }
}
