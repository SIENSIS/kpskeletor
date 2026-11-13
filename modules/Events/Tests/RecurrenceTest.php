<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Modules\Events\Services\Recurrence;

/**
 * Expansion of recurring events for a date range (never stored): frequencies, intervals, days of
 * the week, end date, count, skipped dates, months without the day, leap years, all-day events,
 * daylight saving time and long-running series.
 *
 * @internal
 */
final class RecurrenceTest extends CIUnitTestCase
{
    private const ZONE = 'Europe/Madrid';

    public function testASingleEventOverlappingTheRange(): void
    {
        $event = $this->event('2026-03-10 09:00:00', '2026-03-10 10:00:00');

        $this->assertSame(['2026-03-10 10:00'], $this->starts($event, '2026-03-01', '2026-04-01'), 'UTC 09:00 is 10:00 in Madrid (winter).');
        $this->assertSame([], $this->starts($event, '2026-03-11', '2026-04-01'));
        $this->assertSame([], $this->starts($event, '2026-02-01', '2026-03-10'));
    }

    public function testWithoutAnEndAnEventLastsOneHourOrTheWholeDay(): void
    {
        $recurrence = new Recurrence(self::ZONE);

        [$start, $end] = $recurrence->firstOccurrence($this->event('2026-03-10 09:00:00', null));
        $this->assertSame('2026-03-10 11:00', $end->format('Y-m-d H:i'));

        [$start, $end] = $recurrence->firstOccurrence($this->event('2026-03-09 23:00:00', '2026-03-11 23:00:00', ['all_day' => 1]));
        $this->assertSame('2026-03-10 00:00', $start->format('Y-m-d H:i'), 'All-day events cover whole local days.');
        $this->assertSame('2026-03-13 00:00', $end->format('Y-m-d H:i'), 'The end is exclusive.');
    }

    public function testDailyWithIntervalAndCount(): void
    {
        $event = $this->event('2026-03-02 08:00:00', '2026-03-02 09:00:00', ['recur_freq' => 'daily', 'recur_interval' => 2, 'recur_count' => 4]);

        $this->assertSame(['2026-03-02 09:00', '2026-03-04 09:00', '2026-03-06 09:00', '2026-03-08 09:00'], $this->starts($event, '2026-03-01', '2026-04-01'));
        $this->assertSame(['2026-03-06 09:00', '2026-03-08 09:00'], $this->starts($event, '2026-03-05', '2026-04-01'), 'The count runs from the first occurrence.');
    }

    public function testWeeklyOnSomeDaysUntilADateWithExceptions(): void
    {
        $event = $this->event('2026-03-02 08:00:00', '2026-03-02 09:00:00', [
            'recur_freq' => 'weekly', 'recur_days' => 'mo,th', 'recur_until' => '2026-03-19', 'recur_exceptions' => "2026-03-09\n2026-03-12",
        ]);

        $this->assertSame(['2026-03-02 09:00', '2026-03-05 09:00', '2026-03-16 09:00', '2026-03-19 09:00'], $this->starts($event, '2026-03-01', '2026-04-01'));
    }

    public function testExceptionsCountTowardsTheCountLikeIcal(): void
    {
        $event = $this->event('2026-03-02 08:00:00', null, ['recur_freq' => 'weekly', 'recur_count' => 3, 'recur_exceptions' => '2026-03-09']);

        $this->assertSame(['2026-03-02 09:00', '2026-03-16 09:00'], $this->starts($event, '2026-03-01', '2026-05-01'));
    }

    public function testEveryOtherWeekStartsFromTheFirstWeek(): void
    {
        // Wednesday 4 March; weekly on Monday and Wednesday every 2 weeks: Monday 2 is before the start.
        $event = $this->event('2026-03-04 08:00:00', null, ['recur_freq' => 'weekly', 'recur_interval' => 2, 'recur_days' => 'mo,we']);

        $this->assertSame(['2026-03-04 09:00', '2026-03-16 09:00', '2026-03-18 09:00', '2026-03-30 09:00'], $this->starts($event, '2026-03-01', '2026-04-01'));
    }

    public function testMonthlySkipsMonthsWithoutTheDay(): void
    {
        $event = $this->event('2026-01-31 08:00:00', null, ['recur_freq' => 'monthly']);

        $this->assertSame(['2026-01-31 09:00', '2026-03-31 09:00', '2026-05-31 09:00'], $this->starts($event, '2026-01-01', '2026-06-01'), 'No February or April; still 09:00 after the summer time change.');
    }

    public function testYearlyOnTheTwentyNinthOfFebruary(): void
    {
        $event = $this->event('2024-02-29 00:00:00', null, ['recur_freq' => 'yearly', 'all_day' => 1]);

        $this->assertSame(['2024-02-29 00:00', '2028-02-29 00:00'], $this->starts($event, '2024-01-01', '2029-01-01'));
    }

    public function testTheLocalTimeIsKeptAcrossDaylightSaving(): void
    {
        $event = $this->event('2026-03-23 08:00:00', null, ['recur_freq' => 'weekly']);

        $this->assertSame(['2026-03-23 09:00', '2026-03-30 09:00'], $this->starts($event, '2026-03-20', '2026-04-05'), '09:00 before and after the change.');
    }

    public function testAnOccurrenceStartedBeforeTheRangeIsIncluded(): void
    {
        $event = $this->event('2026-03-01 23:00:00', '2026-03-03 23:00:00', ['recur_freq' => 'weekly', 'all_day' => 1]);

        $this->assertSame(['2026-03-02 00:00'], $this->starts($event, '2026-03-04', '2026-03-05'), 'A three-day event from Monday still runs on Wednesday.');
    }

    public function testLongSeriesJumpToTheRangeAndAreCapped(): void
    {
        $daily = $this->event('2000-01-01 08:00:00', null, ['recur_freq' => 'daily']);
        $this->assertSame(['2026-03-10 09:00', '2026-03-11 09:00'], $this->starts($daily, '2026-03-10', '2026-03-12'), 'Without a count, 26 years are skipped.');

        $weekly = $this->event('2000-01-03 08:00:00', null, ['recur_freq' => 'weekly']);
        $this->assertSame(['2026-03-09 09:00'], $this->starts($weekly, '2026-03-08', '2026-03-12'));

        $capped = new Recurrence(self::ZONE, 10);
        $this->assertCount(10, $capped->between($this->event('2026-01-01 08:00:00', null, ['recur_freq' => 'daily', 'recur_count' => 500]), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
    }

    public function testExceptionsAreNormalised(): void
    {
        $this->assertSame("2026-01-02\n2026-03-04", Recurrence::normaliseExceptions('2026-03-04, nope 2026-01-02;2026-03-04'));
        $this->assertNull(Recurrence::normaliseExceptions('nothing'));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function event(string $start, ?string $end, array $values = []): array
    {
        return $values + ['starts_at' => $start, 'ends_at' => $end, 'all_day' => 0, 'recur_freq' => null, 'recur_interval' => 1];
    }

    /**
     * Local starts of the occurrences in `[$from, $to)` (local dates).
     *
     * @param array<string, mixed> $event
     *
     * @return list<string>
     */
    private function starts(array $event, string $from, string $to): array
    {
        $zone = new DateTimeZone(self::ZONE);

        return array_map(
            static fn (array $occurrence): string => $occurrence['start']->format('Y-m-d H:i'),
            (new Recurrence(self::ZONE))->between($event, new DateTimeImmutable($from, $zone), new DateTimeImmutable($to, $zone)),
        );
    }
}
