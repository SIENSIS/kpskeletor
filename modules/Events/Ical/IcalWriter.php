<?php

declare(strict_types=1);

namespace Modules\Events\Ical;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\Recurrence;

/**
 * Writes iCalendar (RFC 5545) documents of the calendar (docs/events.md, "iCal"):
 *
 * - `VCALENDAR` with `METHOD:PUBLISH`, the calendar's name, the refresh hints
 *   (`REFRESH-INTERVAL`, `X-PUBLISHED-TTL`) and the `VTIMEZONE` of the site's time zone;
 * - one `VEVENT` per event with a stable `UID`, `SEQUENCE`, `DTSTAMP`/`LAST-MODIFIED` from the
 *   last change (so the same data always gives the same document and the same ETag), local times
 *   with `TZID` (all-day events as dates), recurrences as `RRULE`/`EXDATE` (not expanded),
 *   `STATUS:CANCELLED` for cancelled and recently deleted events, and optional `VALARM`s;
 * - CRLF line endings, escaped texts and lines folded at 75 octets.
 */
final class IcalWriter
{
    private const DAY_CODES = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];

    private readonly DateTimeZone $zone;

    private readonly DateTimeZone $storage;

    private readonly Recurrence $recurrence;

    public function __construct(
        private readonly string $host,
        ?string $timezone = null,
        private readonly string $refresh = 'PT1H',
    ) {
        $this->zone       = new DateTimeZone($timezone ?? (function_exists('site_timezone') ? site_timezone() : 'UTC'));
        $this->storage    = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $this->recurrence = new Recurrence($this->zone->getName());
    }

    /**
     * A whole document.
     *
     * @param list<array<string, mixed>> $events rows of `events` (with texts) or provided rows, plus
     *                                           optional `ical_url` (link) and `ical_alarms` (minutes before)
     */
    public function calendar(string $name, array $events, DateTimeInterface $from, DateTimeInterface $to): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//kpskeletor//Calendar 1.0//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . Folding::text($name),
            'X-WR-TIMEZONE:' . $this->zone->getName(),
        ];

        if (preg_match('/^P(T?\d+[WDHMS])+$/', $this->refresh) === 1) {
            $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:' . $this->refresh;
            $lines[] = 'X-PUBLISHED-TTL:' . $this->refresh;
        }

        $years = [(int) $from->format('Y') - 1, (int) $to->format('Y') + 1];

        foreach ($events as $event) {
            $first    = (int) substr((string) ($event['starts_at'] ?? $event['start'] ?? ''), 0, 4);
            $years[0] = $first > 1900 ? min($years[0], $first) : $years[0];
        }

        $lines = [...$lines, ...VTimezone::lines($this->zone->getName(), $years[0], $years[1])];

        foreach ($events as $event) {
            $lines = [...$lines, ...$this->event($event)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(Folding::fold(...), $lines)) . "\r\n";
    }

    /**
     * The `VEVENT` lines of one event (unfolded).
     *
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    public function event(array $row): array
    {
        $provided = (bool) ($row['provided'] ?? false);

        if ($provided) {
            $start = (new DateTimeImmutable((string) $row['start']))->setTimezone($this->zone);
            $end   = (new DateTimeImmutable((string) $row['end']))->setTimezone($this->zone);
        } else {
            [$start, $end] = $this->recurrence->firstOccurrence($row);
        }

        $allDay   = (bool) ($row['all_day'] ?? false);
        $changed  = $this->utc($row['updated_at'] ?? $row['created_at'] ?? null) ?? $start->setTimezone(new DateTimeZone('UTC'));
        $deleted  = ($row['deleted_at'] ?? null) !== null;
        $status   = $deleted ? 'cancelled' : (string) ($row['status'] ?? 'confirmed');
        $title    = trim((string) ($row['title'] ?? ''));
        $lines    = ['BEGIN:VEVENT', 'UID:' . Folding::text($this->uid($row) . '@' . $this->host)];

        $lines[] = 'DTSTAMP:' . $changed->format('Ymd\THis\Z');

        if (! $provided && ($created = $this->utc($row['created_at'] ?? null)) !== null) {
            $lines[] = 'CREATED:' . $created->format('Ymd\THis\Z');
        }

        if (! $provided) {
            $lines[] = 'LAST-MODIFIED:' . $changed->format('Ymd\THis\Z');
        }

        $lines[] = 'SEQUENCE:' . max(0, (int) ($row['sequence'] ?? 0));

        if ($allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
        } else {
            $lines[] = 'DTSTART;TZID=' . $this->zone->getName() . ':' . $start->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=' . $this->zone->getName() . ':' . $end->format('Ymd\THis');
        }

        if (! $provided) {
            $lines = [...$lines, ...$this->recurrenceLines($row, $start, $allDay)];
        }

        $lines[] = 'SUMMARY:' . Folding::text($title === '' ? '—' : $title);

        $description = self::plainText((string) ($row['description'] ?? ''));

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . Folding::text($description);
        }

        if (is_string($row['location'] ?? null) && trim($row['location']) !== '') {
            $lines[] = 'LOCATION:' . Folding::text(trim($row['location']));
        }

        if (is_string($row['category_name'] ?? null) && $row['category_name'] !== '') {
            $lines[] = 'CATEGORIES:' . Folding::text($row['category_name']);
        }

        $url = $row['ical_url'] ?? null;

        if (is_string($url) && preg_match('#^https?://#', $url) === 1) {
            $lines[] = 'URL:' . str_replace(["\r", "\n"], '', $url);
        }

        $lines[] = 'STATUS:' . match ($status) {
            'cancelled' => 'CANCELLED',
            'tentative' => 'TENTATIVE',
            default     => 'CONFIRMED',
        };
        $lines[] = 'TRANSP:' . ($allDay ? 'TRANSPARENT' : 'OPAQUE');
        $lines[] = 'CLASS:' . (($row['visibility'] ?? 'private') === 'public' ? 'PUBLIC' : 'PRIVATE');

        if (! $deleted && $status !== 'cancelled') {
            foreach ((array) ($row['ical_alarms'] ?? []) as $minutes) {
                $lines[] = 'BEGIN:VALARM';
                $lines[] = 'ACTION:DISPLAY';
                $lines[] = 'DESCRIPTION:' . Folding::text($title === '' ? '—' : $title);
                $lines[] = 'TRIGGER:-PT' . max(0, (int) $minutes) . 'M';
                $lines[] = 'END:VALARM';
            }
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * The stable identifier of an event (before `@host`): `event-<code>` for events created by
     * hand, `<module>-<key>` for published ones, `<calendar>-<key>` for provided ones.
     *
     * @param array<string, mixed> $row
     */
    public function uid(array $row): string
    {
        if ((bool) ($row['provided'] ?? false)) {
            return (string) $row['uid'];
        }

        if (is_string($row['source_module'] ?? null) && $row['source_module'] !== '' && is_string($row['source_key'] ?? null)) {
            return strtolower($row['source_module']) . '-' . $row['source_key'];
        }

        return 'event-' . EventRepository::code((int) $row['id']);
    }

    /**
     * HTML (sanitised rich text) as plain text: paragraphs and line breaks become new lines.
     */
    public static function plainText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $text = (string) preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+/", ' ', $text);
        $text = (string) preg_replace("/\n\s*\n+/", "\n\n", $text);

        return trim($text);
    }

    /**
     * `RRULE` and `EXDATE` of a recurring event.
     *
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function recurrenceLines(array $row, DateTimeImmutable $start, bool $allDay): array
    {
        $frequency = (string) ($row['recur_freq'] ?? '');

        if (! in_array($frequency, Recurrence::FREQUENCIES, true)) {
            return [];
        }

        $rule = ['FREQ=' . strtoupper($frequency)];

        if (($interval = max(1, (int) ($row['recur_interval'] ?? 1))) > 1) {
            $rule[] = 'INTERVAL=' . $interval;
        }

        if ($frequency === 'weekly') {
            $rule[] = 'BYDAY=' . implode(',', array_map(static fn (int $day): string => self::DAY_CODES[$day], Recurrence::weekdays($row, $start)));
        }

        $count = (int) ($row['recur_count'] ?? 0);
        $until = (string) ($row['recur_until'] ?? '');

        if ($count > 0) {
            $rule[] = 'COUNT=' . $count;
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $until) === 1) {
            $rule[] = $allDay
                ? 'UNTIL=' . str_replace('-', '', substr($until, 0, 10))
                : 'UNTIL=' . (new DateTimeImmutable(substr($until, 0, 10) . ' 23:59:59', $this->zone))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        }

        $lines = ['RRULE:' . implode(';', $rule)];
        $dates = array_keys(Recurrence::exceptions((string) ($row['recur_exceptions'] ?? '')));
        sort($dates);

        if ($dates !== []) {
            $lines[] = $allDay
                ? 'EXDATE;VALUE=DATE:' . implode(',', array_map(static fn (string $date): string => str_replace('-', '', $date), $dates))
                : 'EXDATE;TZID=' . $this->zone->getName() . ':' . implode(',', array_map(
                    static fn (string $date): string => str_replace('-', '', $date) . 'T' . $start->format('His'),
                    $dates,
                ));
        }

        return $lines;
    }

    private function utc(mixed $stored): ?DateTimeImmutable
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        return (new DateTimeImmutable($stored, $this->storage))->setTimezone(new DateTimeZone('UTC'));
    }
}
