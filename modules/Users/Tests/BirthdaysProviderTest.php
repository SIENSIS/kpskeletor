<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use DateTimeImmutable;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Users\Calendar\BirthdaysProvider;

/**
 * The "Birthdays" provider (calendar API, pull): off by default, day and month only, active users
 * with a date of birth, 29 February in common years, stable keys.
 *
 * @internal
 */
final class BirthdaysProviderTest extends UsersTestCase
{
    public function testItIsOffByDefault(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->birthDate((int) $user->id, '1990-05-17');

        $this->assertSame([], $this->events($user, '2027-05-01', '2027-06-01'));
    }

    public function testBirthdaysOfActiveUsersAreAllDayEventsWithoutTheYear(): void
    {
        setting('Users.birthdaysCalendar', true);
        $ana    = $this->createUser('ana@example.com', 'user');
        $joan   = $this->createUser('joan@example.com', 'user');
        $banned = $this->createUser('ban@example.com', 'user');
        $this->birthDate((int) $ana->id, '1990-05-17', 'Ana Puig');
        $this->birthDate((int) $joan->id, '1985-08-02');
        $this->birthDate((int) $banned->id, '1980-05-20');
        $banned->ban();

        $events = $this->events($joan, '2027-05-01', '2027-06-01');

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->allDay);
        $this->assertSame('2027-05-17', $events[0]->start->format('Y-m-d'));
        $this->assertSame('users.birthdays', $events[0]->calendar);
        $this->assertSame('private', $events[0]->visibility);
        $this->assertSame('user-' . $ana->id . '-2027', $events[0]->key);
        $this->assertSame('Aniversari de Ana Puig', $events[0]->titleIn('ca'));
        $this->assertStringNotContainsString('1990', $events[0]->titleIn('en'), 'Never the year or the age.');
    }

    public function testTheTwentyNinthOfFebruaryAndRangesOverNewYear(): void
    {
        setting('Users.birthdaysCalendar', true);
        $ana  = $this->createUser('ana@example.com', 'user');
        $joan = $this->createUser('joan@example.com', 'user');
        $this->birthDate((int) $ana->id, '2000-02-29');
        $this->birthDate((int) $joan->id, '1999-01-03');

        $this->assertSame(['2027-02-28'], array_map(static fn (CalendarEvent $e): string => $e->start->format('Y-m-d'), $this->events($joan, '2027-02-01', '2027-03-01')));
        $this->assertSame(['2028-02-29'], array_map(static fn (CalendarEvent $e): string => $e->start->format('Y-m-d'), $this->events($joan, '2028-02-01', '2028-03-01')));
        $this->assertSame(['2028-01-03'], array_map(static fn (CalendarEvent $e): string => $e->start->format('Y-m-d'), $this->events($ana, '2027-12-15', '2028-01-15')));
    }

    private function birthDate(int $userId, string $date, ?string $name = null): void
    {
        db_connect()->table('user_profiles')->insert(['user_id' => $userId, 'birth_date' => $date, 'name' => $name]);
    }

    /**
     * @return list<CalendarEvent>
     */
    private function events(\CodeIgniter\Shield\Entities\User $user, string $from, string $to): array
    {
        $events = (new BirthdaysProvider())->events(new DateTimeImmutable($from), new DateTimeImmutable($to), $user);

        return is_array($events) ? array_values($events) : iterator_to_array($events, false);
    }
}
