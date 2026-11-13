<?php

declare(strict_types=1);

namespace Modules\Events\Tests\Support;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarProvider;

/**
 * A test provider of the fake module "Courses": one deadline tomorrow for every user, a second
 * one only for first@example.com (providers filter by user themselves), and an event for another module's
 * calendar (dropped by the calendar). Counts its calls (cache tests).
 */
final class DeadlinesProvider implements CalendarProvider
{
    public static int $calls = 0;

    public function calendar(): string
    {
        return 'courses.deadlines';
    }

    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
    {
        self::$calls++;
        $tomorrow = new DateTimeImmutable('tomorrow 12:00');

        yield new CalendarEvent(title: 'Lliurament de la pràctica', start: $tomorrow, key: 'task-1', url: '/backend/courses/task/1');

        if ($user->email === 'first@example.com') {
            yield new CalendarEvent(title: 'Tutoria personal', start: $tomorrow->modify('+1 hour'), key: 'tutorial-1');
        }

        yield new CalendarEvent(title: 'Intrús', start: $tomorrow, calendar: 'news.notices', key: 'other');
    }
}
