<?php

declare(strict_types=1);

namespace Modules\Events\Ical;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The `VTIMEZONE` component of a time zone (RFC 5545 section 3.6.5) built from PHP's time zone
 * database: one `STANDARD` or `DAYLIGHT` observance per offset change in the covered years, so
 * clients do not need to know the zone name.
 */
final class VTimezone
{
    /**
     * @return list<string> content lines (unfolded)
     */
    public static function lines(string $timezone, int $fromYear, int $toYear): array
    {
        $zone  = new DateTimeZone($timezone);
        $begin = (new DateTimeImmutable($fromYear . '-01-01 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $end   = (new DateTimeImmutable(($toYear + 1) . '-01-01 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $list  = $zone->getTransitions($begin, $end) ?: [];
        $lines = ['BEGIN:VTIMEZONE', 'TZID:' . $timezone];

        if ($list === []) {
            $offset = $zone->getOffset(new DateTimeImmutable('@' . $begin));
            $list   = [['ts' => $begin, 'offset' => $offset, 'isdst' => false, 'abbr' => '']];
        }

        $previous = null;

        foreach ($list as $i => $transition) {
            $offset = (int) $transition['offset'];
            // The first entry is the state at the start of the range, not a change.
            $from = $i === 0 ? $offset : ($previous ?? $offset);

            if ($i > 0 && $from === $offset && ($list[$i - 1]['isdst'] ?? null) === $transition['isdst']) {
                continue;
            }

            $local = (new DateTimeImmutable('@' . ((int) $transition['ts'] + $from)))->format('Ymd\THis');
            $type  = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';

            $lines[] = 'BEGIN:' . $type;
            $lines[] = 'DTSTART:' . $local;
            $lines[] = 'TZOFFSETFROM:' . self::offset($from);
            $lines[] = 'TZOFFSETTO:' . self::offset($offset);

            if (preg_match('/^[A-Za-z]{2,6}$/', $transition['abbr']) === 1) {
                $lines[] = 'TZNAME:' . $transition['abbr'];
            }

            $lines[] = 'END:' . $type;
            $previous = $offset;
        }

        $lines[] = 'END:VTIMEZONE';

        return $lines;
    }

    /**
     * An UTC offset as `+hhmm` / `-hhmm` (seconds are not used by real zones today).
     */
    public static function offset(int $seconds): string
    {
        $sign    = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%02d%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
