<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Core\Scheduler\CronExpression;
use Modules\Core\Scheduler\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cron expressions (fields, lists, ranges, steps, names, macros, the day-of-month / day-of-week
 * rule, next runs) and the schedule helpers and options.
 *
 * @internal
 */
final class CronExpressionTest extends CIUnitTestCase
{
    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    public function testEveryMinuteMatchesAnyMinute(): void
    {
        $cron = new CronExpression('* * * * *');

        $this->assertTrue($cron->matches(self::at('2026-11-06 12:34:56')));
        $this->assertSame('2026-11-06 12:35', $cron->next(self::at('2026-11-06 12:34:56'))->format('Y-m-d H:i'));
    }

    public function testStepsListsAndRanges(): void
    {
        $cron = new CronExpression('*/15 9-17 * * 1-5');

        $this->assertTrue($cron->matches(self::at('2026-11-06 09:45')), 'Friday 09:45');
        $this->assertFalse($cron->matches(self::at('2026-11-06 09:50')));
        $this->assertFalse($cron->matches(self::at('2026-11-06 18:00')));
        $this->assertFalse($cron->matches(self::at('2026-11-07 10:00')), 'Saturday');
        $this->assertSame('2026-11-09 09:00', $cron->next(self::at('2026-11-06 17:45'))->format('Y-m-d H:i'), 'Next Monday');

        $list = new CronExpression('5,35 0-20/10 * * *');
        $this->assertTrue($list->matches(self::at('2026-01-01 20:35')));
        $this->assertFalse($list->matches(self::at('2026-01-01 21:35')));
        $this->assertTrue((new CronExpression('5/20 * * * *'))->matches(self::at('2026-01-01 00:45')), '5/20 = 5, 25, 45');
    }

    public function testNamesSundayAndMacros(): void
    {
        $this->assertTrue((new CronExpression('0 8 * jan,feb sun'))->matches(self::at('2026-02-01 08:00')));
        $this->assertTrue((new CronExpression('0 8 * * 7'))->matches(self::at('2026-02-01 08:00')), '7 is Sunday too');
        $this->assertSame('0 0 * * *', (new CronExpression('@daily'))->expression());
        $this->assertSame('2026-12-01 00:00', (new CronExpression('@monthly'))->next(self::at('2026-11-06 12:00'))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 13:00', (new CronExpression('@hourly'))->next(self::at('2026-11-06 12:00'))->format('Y-m-d H:i'));
    }

    public function testDayOfMonthOrDayOfWeekWhenBothAreRestricted(): void
    {
        $cron = new CronExpression('0 0 13 * 5'); // the 13th, or any Friday

        $this->assertTrue($cron->matches(self::at('2026-11-13 00:00')));
        $this->assertTrue($cron->matches(self::at('2026-11-06 00:00')), 'a Friday');
        $this->assertFalse($cron->matches(self::at('2026-11-05 00:00')));
        $this->assertSame('2026-11-13 00:00', $cron->next(self::at('2026-11-06 00:00'))->format('Y-m-d H:i'));
    }

    public function testNextSkipsMonthsAndLeapDays(): void
    {
        $this->assertSame('2028-02-29 06:30', (new CronExpression('30 6 29 2 *'))->next(self::at('2026-03-01 00:00'))->format('Y-m-d H:i'));
        $this->assertSame('2027-01-31 00:00', (new CronExpression('0 0 31 * *'))->next(self::at('2026-12-31 00:00'))->format('Y-m-d H:i'));
    }

    public function testImpossibleExpressionNeverMatches(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CronExpression('0 0 30 2 *'))->next(self::at('2026-01-01 00:00'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidExpressions(): iterable
    {
        yield 'four fields' => ['* * * *'];
        yield 'minute 60' => ['60 * * * *'];
        yield 'hour 24' => ['0 24 * * *'];
        yield 'day 0' => ['0 0 0 * *'];
        yield 'month 13' => ['0 0 1 13 *'];
        yield 'weekday 8' => ['0 0 * * 8'];
        yield 'reversed range' => ['0 5-1 * * *'];
        yield 'zero step' => ['*/0 * * * *'];
        yield 'garbage' => ['every day'];
        yield 'injection' => ['* * * * *; rm -rf /'];
    }

    #[DataProvider('invalidExpressions')]
    public function testInvalidExpressionsAreRefused(string $expression): void
    {
        $this->assertFalse(CronExpression::isValid($expression));
    }

    public function testScheduleHelpers(): void
    {
        $this->assertSame('* * * * *', Schedule::everyMinute()->expression());
        $this->assertSame('*/15 * * * *', Schedule::everyMinutes(15)->expression());
        $this->assertSame('0 * * * *', Schedule::hourly()->expression());
        $this->assertSame('30 * * * *', Schedule::hourlyAt(30)->expression());
        $this->assertSame('0 3 * * *', Schedule::daily('03:00')->expression());
        $this->assertSame('15 8 * * 1', Schedule::weekly(1, '08:15')->expression());
        $this->assertSame('0 4 1 * *', Schedule::monthly(1, '04:00')->expression());
        $this->assertSame('0 0 * * *', Schedule::cron('@daily')->expression());

        $this->assertTrue(Schedule::daily('03:00')->isDue(self::at('2026-11-06 03:00:42')));
        $this->assertSame('2026-11-07 03:00', Schedule::daily('03:00')->nextRun(self::at('2026-11-06 03:00'))->format('Y-m-d H:i'));
    }

    public function testScheduleOptions(): void
    {
        $plain = Schedule::hourly();
        $this->assertFalse($plain->preventsOverlapping());
        $this->assertTrue($plain->runsInEnvironment('testing'));

        $locked = Schedule::hourly()->withoutOverlapping()->onlyInEnvironment('production');
        $this->assertTrue($locked->preventsOverlapping());
        $this->assertSame(Schedule::LOCK_MINUTES, $locked->lockMinutes());
        $this->assertFalse($locked->runsInEnvironment('testing'));
        $this->assertTrue($locked->runsInEnvironment('production'));
    }

    /**
     * @return iterable<string, array{\Closure(): Schedule}>
     */
    public static function invalidHelpers(): iterable
    {
        yield 'time' => [static fn (): Schedule => Schedule::daily('25:00')];
        yield 'time format' => [static fn (): Schedule => Schedule::daily('3 pm')];
        yield 'minutes' => [static fn (): Schedule => Schedule::everyMinutes(0)];
        yield 'minute' => [static fn (): Schedule => Schedule::hourlyAt(60)];
        yield 'weekday' => [static fn (): Schedule => Schedule::weekly(8)];
        yield 'month day' => [static fn (): Schedule => Schedule::monthly(32)];
        yield 'cron' => [static fn (): Schedule => Schedule::cron('nope')];
    }

    /**
     * @param \Closure(): Schedule $make
     */
    #[DataProvider('invalidHelpers')]
    public function testInvalidHelperArgumentsAreRefused(\Closure $make): void
    {
        $this->expectException(InvalidArgumentException::class);

        $make();
    }
}
