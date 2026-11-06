<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A standard five-field cron expression: minute, hour, day of month, month, day of week.
 *
 * Each field accepts `*`, numbers, lists (`1,15`), ranges (`1-5`), steps (`*\/15`, `0-30/10`) and,
 * for months and days of the week, names (`jan`, `mon`). Day of week 0 and 7 are Sunday. The
 * macros `@yearly`, `@annually`, `@monthly`, `@weekly`, `@daily`, `@midnight` and `@hourly` are
 * accepted. As in Vixie cron, when both the day of month and the day of week are restricted, a
 * day matches when either of them matches.
 */
final class CronExpression
{
    private const MACROS = [
        '@yearly'   => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly'  => '0 0 1 * *',
        '@weekly'   => '0 0 * * 0',
        '@daily'    => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly'   => '0 * * * *',
    ];

    /** Field ranges: [min, max]. */
    private const RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

    private const NAMES = [
        3 => ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12],
        4 => ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6],
    ];

    /** Upper bound of the search for the next run (minutes in about four years and a day). */
    private const MAX_STEPS = 2_200_000;

    private readonly string $expression;

    /** @var array<int, array<int, true>> Allowed values of each field */
    private array $allowed = [];

    /** Whether the day-of-month and day-of-week fields are restricted (not `*`). */
    private bool $domRestricted;

    private bool $dowRestricted;

    /**
     * @throws InvalidArgumentException for an invalid expression
     */
    public function __construct(string $expression)
    {
        $normalised = strtolower(trim((string) preg_replace('/\s+/', ' ', $expression)));
        $normalised = self::MACROS[$normalised] ?? $normalised;
        $fields     = explode(' ', $normalised);

        if (count($fields) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression \"{$expression}\": five fields expected (minute hour day month weekday).");
        }

        foreach ($fields as $index => $field) {
            $this->allowed[$index] = $this->parseField($field, $index, $expression);
        }

        // Sunday is 0 and 7.
        if (isset($this->allowed[4][7])) {
            $this->allowed[4][0] = true;
            unset($this->allowed[4][7]);
        }

        $this->domRestricted = ! str_starts_with($fields[2], '*');
        $this->dowRestricted = ! str_starts_with($fields[4], '*');
        $this->expression    = $normalised;
    }

    public static function isValid(string $expression): bool
    {
        try {
            new self($expression);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** The expression with the macros expanded (e.g. "0 0 * * *"). */
    public function expression(): string
    {
        return $this->expression;
    }

    /**
     * Whether the expression matches the minute of the given time (seconds are ignored).
     */
    public function matches(DateTimeInterface $time): bool
    {
        if (! isset($this->allowed[0][(int) $time->format('i')], $this->allowed[1][(int) $time->format('G')], $this->allowed[3][(int) $time->format('n')])) {
            return false;
        }

        return $this->dayMatches($time);
    }

    /**
     * The first matching minute strictly after the given time.
     *
     * @throws InvalidArgumentException when the expression never matches (e.g. 30 February)
     */
    public function next(DateTimeInterface $after): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromInterface($after);
        $time = $time->setTime((int) $time->format('G'), (int) $time->format('i'))->modify('+1 minute');

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            if (! isset($this->allowed[3][(int) $time->format('n')])) {
                $time = $time->modify('first day of next month')->setTime(0, 0);

                continue;
            }

            if (! $this->dayMatches($time)) {
                $time = $time->modify('+1 day')->setTime(0, 0);

                continue;
            }

            if (! isset($this->allowed[1][(int) $time->format('G')])) {
                $time = $time->setTime((int) $time->format('G'), 0)->modify('+1 hour');

                continue;
            }

            if (! isset($this->allowed[0][(int) $time->format('i')])) {
                $time = $time->modify('+1 minute');

                continue;
            }

            return $time;
        }

        throw new InvalidArgumentException("The cron expression \"{$this->expression}\" never matches.");
    }

    private function dayMatches(DateTimeInterface $time): bool
    {
        $dom = isset($this->allowed[2][(int) $time->format('j')]);
        $dow = isset($this->allowed[4][(int) $time->format('w')]);

        if ($this->domRestricted && $this->dowRestricted) {
            return $dom || $dow;
        }

        return $dom && $dow;
    }

    /**
     * @return array<int, true>
     */
    private function parseField(string $field, int $index, string $expression): array
    {
        [$min, $max] = self::RANGES[$index];
        $values      = [];

        foreach (explode(',', $field) as $part) {
            if (preg_match('/^(\*|[a-z0-9]+(?:-[a-z0-9]+)?)(?:\/(\d+))?$/', $part, $match) !== 1) {
                throw new InvalidArgumentException("Invalid cron expression \"{$expression}\": \"{$part}\" is not valid.");
            }

            $step = isset($match[2]) ? (int) $match[2] : 1;

            if ($step < 1) {
                throw new InvalidArgumentException("Invalid cron expression \"{$expression}\": the step of \"{$part}\" must be at least 1.");
            }

            if ($match[1] === '*') {
                [$from, $to] = [$min, $index === 4 ? 6 : $max];
            } elseif (str_contains($match[1], '-')) {
                [$a, $b]     = explode('-', $match[1], 2);
                [$from, $to] = [$this->value($a, $index, $expression), $this->value($b, $index, $expression)];
            } else {
                $from = $this->value($match[1], $index, $expression);
                // "5/15": from 5 to the end, every 15.
                $to = isset($match[2]) ? $max : $from;
            }

            if ($from > $to) {
                throw new InvalidArgumentException("Invalid cron expression \"{$expression}\": the range \"{$part}\" is reversed.");
            }

            for ($value = $from; $value <= $to; $value += $step) {
                $values[$value] = true;
            }
        }

        return $values;
    }

    private function value(string $text, int $index, string $expression): int
    {
        if (isset(self::NAMES[$index][$text])) {
            return self::NAMES[$index][$text];
        }

        [$min, $max] = self::RANGES[$index];

        if (! ctype_digit($text) || (int) $text < $min || (int) $text > $max) {
            throw new InvalidArgumentException("Invalid cron expression \"{$expression}\": \"{$text}\" is out of range ({$min}-{$max}).");
        }

        return (int) $text;
    }
}
