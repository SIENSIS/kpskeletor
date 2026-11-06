<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * When a scheduled task runs, and its options (docs/scheduler.md).
 *
 * ```php
 * public function schedule(): Schedule
 * {
 *     return Schedule::daily('03:00')->withoutOverlapping();
 * }
 * ```
 *
 * Helpers: `cron()`, `everyMinute()`, `everyMinutes(15)`, `hourly()`, `hourlyAt(30)`,
 * `daily('03:00')`, `weekly(1, '08:00')`, `monthly(1, '04:00')`. Times are in the application's
 * time zone (`Config\App::$appTimezone`).
 */
final class Schedule
{
    /** Default lifetime of a `withoutOverlapping()` lock, in minutes. */
    public const LOCK_MINUTES = 60;

    private CronExpression $cron;

    private ?int $lockMinutes = null;

    /** @var list<string> */
    private array $environments = [];

    private function __construct(string $expression)
    {
        $this->cron = new CronExpression($expression);
    }

    /**
     * A five-field cron expression or a macro (`@daily`).
     *
     * @throws InvalidArgumentException for an invalid expression
     */
    public static function cron(string $expression): self
    {
        return new self($expression);
    }

    public static function everyMinute(): self
    {
        return new self('* * * * *');
    }

    /**
     * Every N minutes (a divisor of 60 runs at regular times: 0, 15, 30, 45).
     */
    public static function everyMinutes(int $minutes): self
    {
        if ($minutes < 1 || $minutes > 59) {
            throw new InvalidArgumentException('everyMinutes() takes 1 to 59 minutes.');
        }

        return new self($minutes === 1 ? '* * * * *' : "*/{$minutes} * * * *");
    }

    public static function hourly(): self
    {
        return new self('0 * * * *');
    }

    public static function hourlyAt(int $minute): self
    {
        return new self(self::minute($minute) . ' * * * *');
    }

    /**
     * Every day at a time ("HH:MM", default midnight).
     */
    public static function daily(string $time = '00:00'): self
    {
        [$hour, $minute] = self::time($time);

        return new self("{$minute} {$hour} * * *");
    }

    /**
     * Every week on a day (0 or 7 = Sunday, 1 = Monday…) at a time.
     */
    public static function weekly(int $day = 1, string $time = '00:00'): self
    {
        if ($day < 0 || $day > 7) {
            throw new InvalidArgumentException('weekly() takes a day from 0 (Sunday) to 7 (Sunday).');
        }

        [$hour, $minute] = self::time($time);

        return new self("{$minute} {$hour} * * {$day}");
    }

    /**
     * Every month on a day (1-28 runs every month) at a time.
     */
    public static function monthly(int $day = 1, string $time = '00:00'): self
    {
        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException('monthly() takes a day from 1 to 31.');
        }

        [$hour, $minute] = self::time($time);

        return new self("{$minute} {$hour} {$day} * *");
    }

    /**
     * Never run two copies at once: the run holds a lock (in the database, so it works on every
     * host) that expires after the given minutes in case the process dies.
     */
    public function withoutOverlapping(int $expiresAfterMinutes = self::LOCK_MINUTES): self
    {
        $this->lockMinutes = max(1, $expiresAfterMinutes);

        return $this;
    }

    /**
     * Run only in these environments (`ENVIRONMENT`: production, development, testing).
     */
    public function onlyInEnvironment(string ...$environments): self
    {
        $this->environments = array_values($environments);

        return $this;
    }

    public function expression(): string
    {
        return $this->cron->expression();
    }

    public function cronExpression(): CronExpression
    {
        return $this->cron;
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $this->cron->matches($time);
    }

    public function nextRun(DateTimeInterface $after): DateTimeImmutable
    {
        return $this->cron->next($after);
    }

    public function preventsOverlapping(): bool
    {
        return $this->lockMinutes !== null;
    }

    /** Lock lifetime in minutes (null: no lock). */
    public function lockMinutes(): ?int
    {
        return $this->lockMinutes;
    }

    /** @return list<string> Environments it is limited to (empty: all) */
    public function environments(): array
    {
        return $this->environments;
    }

    public function runsInEnvironment(string $environment): bool
    {
        return $this->environments === [] || in_array($environment, $this->environments, true);
    }

    private static function minute(int $minute): int
    {
        if ($minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('The minute must be from 0 to 59.');
        }

        return $minute;
    }

    /**
     * @return array{int, int} hour, minute
     */
    private static function time(string $time): array
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $match) !== 1) {
            throw new InvalidArgumentException("Invalid time \"{$time}\" (expected HH:MM).");
        }

        return [(int) $match[1], (int) $match[2]];
    }
}
