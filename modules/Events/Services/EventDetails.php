<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The details page of an event (back office and public site): the occurrence shown (the one of
 * `?date=YYYY-MM-DD`, else the next one, else the first) and the texts of its dates and
 * recurrence.
 */
final class EventDetails
{
    /**
     * @param array<string, mixed> $event a row of `EventRepository::find()`
     *
     * @return array<string, mixed> the event plus `start`, `end` (DateTimeImmutable, site time
     *                              zone), `when` and `recurrence` (texts)
     */
    public static function build(array $event, ?string $date): array
    {
        $recurrence = new Recurrence(maxOccurrences: (int) config(\Modules\Events\Config\Calendar::class)->maxOccurrences);
        $zone       = new DateTimeZone(site_timezone());
        $occurrence = null;

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $day        = new DateTimeImmutable($date, $zone);
            $occurrence = $recurrence->between($event, $day, $day->modify('+1 day'))[0] ?? null;
        }

        if ($occurrence === null) {
            $now        = new DateTimeImmutable('now', $zone);
            $occurrence = $recurrence->next($event, $now, $now->modify('+2 years'));
        }

        if ($occurrence === null) {
            [$start, $end] = $recurrence->firstOccurrence($event);
            $occurrence    = ['start' => $start, 'end' => $end];
        }

        return $event + [
            'start'      => $occurrence['start'],
            'end'        => $occurrence['end'],
            'when'       => self::when($occurrence['start'], $occurrence['end'], (bool) $event['all_day']),
            'recurrence' => self::recurrence($event, $occurrence['start']),
        ];
    }

    /**
     * "12/11/2026 09:00 – 10:30", "12/11/2026 (all day)", "12/11/2026 – 14/11/2026".
     */
    public static function when(DateTimeImmutable $start, DateTimeImmutable $end, bool $allDay): string
    {
        if ($allDay) {
            $last = $end->modify('-1 day');

            return $last->format('Y-m-d') === $start->format('Y-m-d')
                ? lang('Events.allDayOn', [ui_date($start, 'date')])
                : ui_date($start, 'date') . ' – ' . ui_date($last, 'date');
        }

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return ui_date($start, 'datetime') . ' – ' . $end->format('H:i');
        }

        return ui_date($start, 'datetime') . ' – ' . ui_date($end, 'datetime');
    }

    /**
     * A sentence describing the recurrence, or null for a single event.
     *
     * @param array<string, mixed> $event
     */
    public static function recurrence(array $event, DateTimeImmutable $start): ?string
    {
        $frequency = (string) ($event['recur_freq'] ?? '');

        if (! in_array($frequency, Recurrence::FREQUENCIES, true)) {
            return null;
        }

        $interval = max(1, (int) ($event['recur_interval'] ?? 1));
        $text     = lang('Events.every.' . $frequency, [$interval]);

        if ($frequency === 'weekly') {
            $names = [];

            foreach (Recurrence::weekdays($event, $start) as $day) {
                $names[] = lang('Events.days.' . array_search($day, Recurrence::DAYS, true));
            }

            $text .= ' (' . implode(', ', $names) . ')';
        }

        if (($event['recur_until'] ?? null) !== null && $event['recur_until'] !== '') {
            $text .= ', ' . lang('Events.untilDate', [ui_date((string) $event['recur_until'], 'date')]);
        } elseif ((int) ($event['recur_count'] ?? 0) > 0) {
            $text .= ', ' . lang('Events.times', [(int) $event['recur_count']]);
        }

        return $text;
    }
}
