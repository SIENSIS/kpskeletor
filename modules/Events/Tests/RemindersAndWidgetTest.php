<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\I18n\Time;
use Modules\Events\Tasks\SendReminders;
use Modules\Events\Widgets\UpcomingEvents;

/**
 * Reminders (asked in the event details, sent once per occurrence by the task, only while the
 * user can see the event) and the "Upcoming events" widget (only visible events).
 *
 * @internal
 */
final class RemindersAndWidgetTest extends EventsTestCase
{
    public function testAUserAsksForAReminderAndTheTaskSendsItOnce(): void
    {
        $ana = $this->loginWith();
        $soon = $this->event('Reunió aviat', ['starts_at' => Time::now()->addMinutes(30)->toDateTimeString(), 'ends_at' => Time::now()->addMinutes(90)->toDateTimeString()]);
        $later = $this->event('Reunió demà');

        $page = $this->body('GET', 'backend/calendar/event/' . $this->code($soon));
        $this->assertStringContainsString('name="minutes"', $page);

        $this->send('POST', 'backend/calendar/event/' . $this->code($soon) . '/reminder', ['minutes' => '60'])->assertRedirect();
        $this->send('POST', 'backend/calendar/event/' . $this->code($later) . '/reminder', ['minutes' => '15'])->assertRedirect();
        $this->assertSame(2, db_connect()->table('event_reminders')->where('user_id', $ana->id)->countAllResults());

        // Only the offered values.
        $this->send('POST', 'backend/calendar/event/' . $this->code($later) . '/reminder', ['minutes' => '7']);
        $this->assertSame('15', (string) db_connect()->table('event_reminders')->where('event_id', $later)->get()->getRow()->minutes_before);

        $result = (new SendReminders())->run();
        $this->assertFalse($result->failed());
        $this->assertSame(1, db_connect()->table('notifications')->where('user_id', $ana->id)->countAllResults(), 'Only the event starting within its reminder time.');

        (new SendReminders())->run();
        $this->assertSame(1, db_connect()->table('notifications')->where('user_id', $ana->id)->countAllResults(), 'Never twice for the same occurrence.');

        // Removing it.
        $this->send('POST', 'backend/calendar/event/' . $this->code($later) . '/reminder', ['minutes' => ''])->assertRedirect();
        $this->assertSame(0, db_connect()->table('event_reminders')->where('event_id', $later)->countAllResults());
    }

    public function testNoReminderForEventsTheUserCannotSee(): void
    {
        $ana    = $this->createUser('ana@example.com', 'user');
        $hidden = $this->event('Per als editors', [
            'visibility' => 'restricted',
            'starts_at'  => Time::now()->addMinutes(10)->toDateTimeString(),
        ], ['editor']);
        $this->login('ana@example.com');

        $this->assertNotFound('POST', 'backend/calendar/event/' . $this->code($hidden) . '/reminder', ['minutes' => '60']);
        $this->assertSame(0, db_connect()->table('event_reminders')->countAllResults());

        // A reminder saved while the user could see the event is not sent after losing access.
        db_connect()->table('event_reminders')->insert(['event_id' => $hidden, 'user_id' => $ana->id, 'minutes_before' => 60]);
        (new SendReminders())->run();
        $this->assertSame(0, db_connect()->table('notifications')->where('user_id', $ana->id)->countAllResults());
    }

    public function testTheWidgetShowsTheNextVisibleEvents(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('ana@example.com', 'user');
        $this->event('Per a tothom');
        $this->event('Només per al John', ['visibility' => 'restricted'], [], [(int) $john->id]);
        $this->event('Anul·lada', ['status' => 'cancelled']);
        $this->event('Ja passada', ['starts_at' => Time::now()->subDays(2)->toDateTimeString(), 'ends_at' => Time::now()->subDays(2)->addHours(1)->toDateTimeString()]);

        $this->login('ana@example.com');
        $this->assertContains('events.upcoming', $this->cards($this->send('GET', 'backend')));
        $html = (new UpcomingEvents())->render(['limit' => 5]);

        $this->assertStringContainsString('Per a tothom', $html);
        $this->assertStringNotContainsString('Només per al John', $html);
        $this->assertStringNotContainsString('Anul·lada', $html);
        $this->assertStringNotContainsString('Ja passada', $html);

        $this->send('POST', 'logout');
        $this->login('john@example.com');
        $this->assertStringContainsString('Només per al John', (new UpcomingEvents())->render(['limit' => 5]));
    }
}
