<?php

declare(strict_types=1);

namespace Modules\Events\Tests\Support;

use DateTimeImmutable;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarSource;

/**
 * A test calendar source of the fake module "Courses": the lessons in `$lessons` (key => title).
 */
final class LessonsSource implements CalendarSource
{
    /** @var array<string, string> */
    public static array $lessons = [];

    public function all(): iterable
    {
        foreach (self::$lessons as $key => $title) {
            yield $key => new CalendarEvent(title: $title, start: new DateTimeImmutable('tomorrow 09:00'), calendar: 'courses.lessons', visibility: 'private');
        }
    }
}
