<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\I18n\Time;
use Modules\News\Services\NoticeCalendar;
use Modules\News\Services\Publisher;

/**
 * Notices in the calendar (calendar API, push; docs/news.md, "Calendar"): a published notice with
 * an event date is in the `news.notices` calendar with the notice's audience; unpublishing,
 * trashing, expiring or removing the date takes it out; `calendar:resync News` repairs drift.
 *
 * @internal
 */
final class NoticeCalendarTest extends NewsTestCase
{
    public function testAPublishedNoticeWithADateIsPublishedWithItsAudience(): void
    {
        $date = Time::tomorrow()->setHour(10)->toDateTimeString();
        $id   = $this->newsPost('Jornada de portes obertes', ['type' => 'notice', 'visibility' => 'groups', 'event_starts_at' => $date], ['editor']);

        $this->assertTrue(NoticeCalendar::sync($id));
        $event = $this->calendarEvent($id);
        $this->assertSame('news.notices', $event['calendar']);
        $this->assertSame('restricted', $event['visibility'], 'groups → restricted (D-00-41).');
        $this->assertSame($date, $event['starts_at']);
        $this->assertSame('/backend/news/read/' . $this->code($id), $event['source_url']);
        $this->assertSame(1, (int) $event['allow_reminders']);
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $event['id'])->where('group_id IS NOT NULL')->countAllResults());
        $this->assertFalse(NoticeCalendar::sync($id), 'Idempotent.');

        foreach (['public' => 'public', 'private' => 'private'] as $news => $calendar) {
            db_connect()->table('news')->where('id', $id)->update(['visibility' => $news]);
            NoticeCalendar::sync($id);
            $this->assertSame($calendar, $this->calendarEvent($id)['visibility']);
        }
    }

    public function testNoticesLeaveTheCalendarWhenTheyAreNoLongerPublished(): void
    {
        $date    = Time::tomorrow()->setHour(10)->toDateTimeString();
        $article = $this->newsPost('Article', ['event_starts_at' => $date]);
        $draft   = $this->newsPost('Esborrany', ['type' => 'notice', 'status' => 'draft', 'event_starts_at' => $date]);
        $noDate  = $this->newsPost('Sense data', ['type' => 'notice']);

        foreach ([$article, $draft, $noDate] as $id) {
            NoticeCalendar::sync($id);
            $this->assertNull(db_connect()->table('events')->where('source_key', NoticeCalendar::key($id))->get()->getRowArray());
        }

        $id = $this->newsPost('Avís', ['type' => 'notice', 'event_starts_at' => $date, 'expires_at' => Time::now()->addMinutes(5)->toDateTimeString()]);
        NoticeCalendar::sync($id);
        $this->assertNull($this->calendarEvent($id)['deleted_at']);

        Time::setTestNow(Time::now()->addMinutes(10));
        $this->assertSame(1, (new Publisher())->expireNotices());
        $this->assertNotNull($this->calendarEvent($id)['deleted_at'], 'An expired notice leaves the calendar.');
        Time::setTestNow();
    }

    public function testTheFormAndTheOperationsKeepTheCalendarInSync(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $id   = $this->newsPost('Avís', ['type' => 'notice', 'status' => 'draft']);
        $code = $this->code($id);

        $this->send('POST', 'backend/news/posts/edit/' . $code, [
            'kp_tr' => ['ca' => ['title' => 'Avís amb data', 'summary' => 'Resum', 'body' => '']],
            'type' => 'notice', 'priority' => 'info', 'visibility' => 'private', 'slug' => 'avis',
            'event_starts_at' => Time::tomorrow()->setHour(9)->toDateTimeString(), 'event_ends_at' => '', 'event_all_day' => '0',
        ]);
        $this->assertNull(db_connect()->table('events')->where('source_key', NoticeCalendar::key($id))->get()->getRowArray(), 'A draft is not in the calendar.');

        $this->send('POST', 'backend/news/posts/publish/' . $code);
        $this->assertNull($this->calendarEvent($id)['deleted_at']);
        $this->assertSame('Avís amb data', db_connect()->table('event_translations')->where('event_id', $this->calendarEvent($id)['id'])->get()->getRowArray()['title']);

        $this->send('POST', 'backend/news/posts/unpublish/' . $code);
        $this->assertNotNull($this->calendarEvent($id)['deleted_at']);

        $this->send('POST', 'backend/news/posts/publish/' . $code);
        $this->assertNull($this->calendarEvent($id)['deleted_at']);

        $this->send('POST', 'backend/news/posts/delete/' . $code);
        $this->assertNotNull($this->calendarEvent($id)['deleted_at'], 'A post in the trash leaves the calendar.');
    }

    public function testAnEndBeforeTheStartIsRefused(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $id = $this->newsPost('Avís', ['type' => 'notice', 'status' => 'draft']);

        $this->send('POST', 'backend/news/posts/edit/' . $this->code($id), [
            'kp_tr' => ['ca' => ['title' => 'Avís', 'summary' => '', 'body' => '']],
            'type' => 'notice', 'priority' => 'info', 'visibility' => 'private', 'slug' => 'avis',
            'event_starts_at' => '2030-01-02 10:00:00', 'event_ends_at' => '2030-01-01 10:00:00',
        ]);
        $this->assertNull($this->row($id)['event_starts_at']);
    }

    public function testResyncRepairsDrift(): void
    {
        $date = Time::tomorrow()->setHour(10)->toDateTimeString();
        $id   = $this->newsPost('Avís', ['type' => 'notice', 'event_starts_at' => $date]);
        $this->assertNull(db_connect()->table('events')->where('source_key', NoticeCalendar::key($id))->get()->getRowArray());

        $result = (new \Modules\Events\Services\CalendarService())->resync('News');
        $this->assertSame(1, $result['published']);
        $this->assertNull($this->calendarEvent($id)['deleted_at']);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarEvent(int $id): array
    {
        $row = db_connect()->table('events')->where('source_module', 'News')->where('source_key', NoticeCalendar::key($id))->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }
}
