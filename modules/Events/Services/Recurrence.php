<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Basic recurrence of events, expanded when a date range is queried and never stored
 * (docs/events.md, "Recurrence"): daily, weekly (on some days of the week), monthly (same day of
 * the month; months without that day are skipped) and yearly (same day; 29 February only in leap
 * years), every `recur_interval` periods, until a date (`recur_until`, inclusive) or a number of
 * occurrences (`recur_count`, counted before the exceptions, like iCal's `COUNT` and `EXDATE`),
 * minus the skipped dates (`recur_exceptions`).
 *
 * Dates are stored in UTC and expanded in the site's time zone, so "every Monday at 9:00" stays at
 * 9:00 across daylight saving changes. An event without `ends_at` lasts one hour (all-day events:
 * one day); all-day events span whole days (end exclusive).
 */
final class Recurrence
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** Days of the week as stored in `recur_days` (ISO order: Monday = 1). */
    public const DAYS = ['mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6, 'su' => 7];

    private readonly DateTimeZone $zone;

    private readonly DateTimeZone $storage;

    public function __construct(?string $timezone = null, private readonly int $maxOccurrences = 1000)
    {
        $this->zone    = new DateTimeZone($timezone ?? (function_exists('site_timezone') ? site_timezone() : 'UTC'));
        $this->storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
    }

    /**
     * The occurrences of an event (a row of `events`) that overlap `[$from, $to)`, in the site's
     * time zone, in order.
     *
     * @param array<string, mixed> $event
     *
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public function between(array $event, DateTimeInterface $from, DateTimeInterface $to): array
    {
        [$start, $end] = $this->firstOccurrence($event);
        $duration      = $start->diff($end);
        $from          = DateTimeImmutable::createFromInterface($from);
        $to            = DateTimeImmutable::createFromInterface($to);
        $frequency     = (string) ($event['recur_freq'] ?? '');

        if (! in_array($frequency, self::FREQUENCIES, true)) {
            return $start < $to && $end > $from ? [['start' => $start, 'end' => $end]] : [];
        }

        $until      = $this->until($event);
        $count      = (int) ($event['recur_count'] ?? 0);
        $exceptions = self::exceptions((string) ($event['recur_exceptions'] ?? ''));
        $found      = [];
        $produced   = 0;

        foreach ($this->candidates($event, $start, $frequency, $count === 0 ? $from->sub($duration) : null) as $candidate) {
            if (($until !== null && $candidate > $until) || $candidate >= $to || ($count > 0 && $produced >= $count) || $produced >= $this->maxOccurrences) {
                break;
            }

            $produced++;
            $occurrenceEnd = $candidate->add($duration);

            if (isset($exceptions[$candidate->format('Y-m-d')]) || $occurrenceEnd <= $from) {
                continue;
            }

            $found[] = ['start' => $candidate, 'end' => $occurrenceEnd];
        }

        return $found;
    }

    /**
     * The first occurrence that starts after `$after` (the widget, reminders), or null.
     *
     * @param array<string, mixed> $event
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null
     */
    public function next(array $event, DateTimeInterface $after, DateTimeInterface $before): ?array
    {
        foreach ($this->between($event, $after, $before) as $occurrence) {
            if ($occurrence['start'] >= $after) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Start and (exclusive) end of the event's first occurrence in the site's time zone.
     *
     * @param array<string, mixed> $event
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function firstOccurrence(array $event): array
    {
        $start  = $this->local((string) $event['starts_at']);
        $end    = ($event['ends_at'] ?? null) === null || $event['ends_at'] === '' ? null : $this->local((string) $event['ends_at']);
        $allDay = (bool) ($event['all_day'] ?? false);

        if ($allDay) {
            $start = $start->setTime(0, 0);
            $end   = ($end === null || $end < $start ? $start : $end->setTime(0, 0))->modify('+1 day');
        } elseif ($end === null || $end <= $start) {
            $end = $start->add(new DateInterval('PT1H'));
        }

        return [$start, $end];
    }

    /**
     * Skipped dates (`YYYY-MM-DD`, one per line or separated by commas) as a set.
     *
     * @return array<string, true>
     */
    public static function exceptions(string $text): array
    {
        $dates = [];

        foreach (preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $dates[$date] = true;
            }
        }

        return $dates;
    }

    /**
     * Normalises the exceptions typed in the form: valid dates, sorted, one per line.
     */
    public static function normaliseExceptions(string $text): ?string
    {
        $dates = array_keys(self::exceptions($text));
        sort($dates);

        return $dates === [] ? null : implode("\n", $dates);
    }

    /**
     * The days of the week of a weekly event (ISO numbers); the start's day when none is stored.
     *
     * @param array<string, mixed> $event
     *
     * @return list<int>
     */
    public static function weekdays(array $event, DateTimeImmutable $start): array
    {
        $days = [];

        foreach (explode(',', strtolower((string) ($event['recur_days'] ?? ''))) as $day) {
            if (isset(self::DAYS[trim($day)])) {
                $days[] = self::DAYS[trim($day)];
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days === [] ? [(int) $start->format('N')] : $days;
    }

    /**
     * Candidate starts, in order, from the first occurrence on. `$skipTo` lets daily and weekly
     * series without a count start near the range instead of iterating from the first date.
     *
     * @param array<string, mixed> $event
     *
     * @return iterable<DateTimeImmutable>
     */
    private function candidates(array $event, DateTimeImmutable $start, string $frequency, ?DateTimeImmutable $skipTo): iterable
    {
        $interval = max(1, (int) ($event['recur_interval'] ?? 1));
        $time     = [(int) $start->format('G'), (int) $start->format('i'), (int) $start->format('s')];

        switch ($frequency) {
            case 'daily':
                $step = 0;

                if ($skipTo !== null && $skipTo > $start) {
                    $step = intdiv(self::daysBetween($start, $skipTo), $interval);
                }

                for (; ; $step++) {
                    yield $start->modify('+' . ($step * $interval) . ' days')->setTime(...$time);
                }

                // no break (infinite generator)

            case 'weekly':
                $days   = self::weekdays($event, $start);
                $monday = $start->modify('-' . ((int) $start->format('N') - 1) . ' days')->setTime(...$time);
                $week   = 0;

                if ($skipTo !== null && $skipTo > $start) {
                    $week = intdiv(intdiv(self::daysBetween($monday, $skipTo), 7), $interval);
                }

                for (; ; $week++) {
                    $base = $monday->modify('+' . ($week * $interval * 7) . ' days');

                    foreach ($days as $day) {
                        $candidate = $base->modify('+' . ($day - 1) . ' days')->setTime(...$time);

                        if ($candidate >= $start) {
                            yield $candidate;
                        }
                    }
                }

                // no break (infinite generator)

            case 'monthly':
                $day = (int) $start->format('j');

                for ($month = 0; ; $month += $interval) {
                    $first = $start->setDate((int) $start->format('Y'), (int) $start->format('n'), 1)->modify('+' . $month . ' months');

                    if ($day <= (int) $first->format('t')) {
                        yield $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->setTime(...$time);
                    }

                    if ($month > 12 * 1000) {
                        return;
                    }
                }

                // no break (infinite generator)

            default: // yearly
                $monthOfYear = (int) $start->format('n');
                $day         = (int) $start->format('j');

                for ($year = (int) $start->format('Y'); $year < 9999; $year += $interval) {
                    if (checkdate($monthOfYear, $day, $year)) {
                        yield $start->setDate($year, $monthOfYear, $day)->setTime(...$time);
                    }
                }
        }
    }

    /**
     * The last moment an occurrence may start (`recur_until` is an inclusive date in the site's
     * time zone), or null.
     *
     * @param array<string, mixed> $event
     */
    private function until(array $event): ?DateTimeImmutable
    {
        $until = (string) ($event['recur_until'] ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $until) !== 1) {
            return null;
        }

        return new DateTimeImmutable(substr($until, 0, 10) . ' 23:59:59', $this->zone);
    }

    private function local(string $stored): DateTimeImmutable
    {
        return (new DateTimeImmutable($stored, $this->storage))->setTimezone($this->zone);
    }

    private static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return max(0, (int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->format('%a') - 1);
    }
}
