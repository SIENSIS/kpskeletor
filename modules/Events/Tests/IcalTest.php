<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\TestResponse;
use Modules\Events\Config\Calendar;
use Modules\Events\Ical\Folding;
use Modules\Events\Ical\IcalWriter;
use Modules\Events\Services\IcalFeeds;
use Modules\Events\Tests\Support\IcalValidator;

/**
 * iCal (docs/events.md, "iCal"): the RFC 5545 output (validated), the personal feed and its
 * secret (shown once, hashed, regenerated, revoked), permissions evaluated on every request,
 * ETag/304, public feeds, one-off exports, cancelled and deleted events, VALARM.
 *
 * @internal
 */
final class IcalTest extends EventsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(Calendar::class)->icalCacheSeconds     = 0;
        config(Calendar::class)->providerCacheSeconds = 0;
    }

    public function testFoldingAndEscapingFollowRfc5545(): void
    {
        $this->assertSame('a\\\\b\;c\\,d\\ne', Folding::text("a\\b;c,d\r\ne"));

        $line   = 'SUMMARY:' . str_repeat('àèò', 40);
        $folded = Folding::fold($line);

        foreach (explode("\r\n", $folded) as $i => $part) {
            $this->assertLessThanOrEqual(75, strlen($part));
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'), 'No character is split.');
            $this->assertSame($i === 0 ? 'S' : ' ', $part[0]);
        }

        $this->assertSame($line, str_replace("\r\n ", '', $folded));
    }

    public function testTheWriterProducesAValidDocumentWithRecurrencesAndTimeZones(): void
    {
        $id = $this->event('Reunió setmanal; equip, sala 2', [
            'starts_at' => '2026-03-02 08:00:00', 'ends_at' => '2026-03-02 09:00:00',
            'recur_freq' => 'weekly', 'recur_interval' => 2, 'recur_days' => 'mo,we', 'recur_until' => '2026-12-31',
            'recur_exceptions' => "2026-03-16\n2026-04-01", 'location' => 'Sala 2', 'sequence' => 3, 'visibility' => 'public',
        ]);
        $this->event('Festa', ['starts_at' => '2026-06-22 22:00:00', 'ends_at' => null, 'all_day' => 1, 'recur_freq' => 'yearly', 'recur_count' => 5, 'recur_exceptions' => '2027-06-24', 'visibility' => 'public']);
        $rows = (new \Modules\Events\Services\EventRepository())->occurrences(null, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01'));
        $rows = array_values(array_column($rows, null, 'id'));

        $ics = (new IcalWriter('example.org', 'Europe/Madrid'))->calendar('Prova', $rows, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01'));

        $this->assertSame([], IcalValidator::validate($ics));
        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Madrid", $ics);
        $this->assertStringContainsString('TZOFFSETTO:+0200', $ics);
        $this->assertStringContainsString('REFRESH-INTERVAL;VALUE=DURATION:PT1H', $ics);
        $this->assertStringContainsString('X-PUBLISHED-TTL:PT1H', $ics);

        $events = IcalValidator::events($ics);
        $weekly = $events[0]['UID'][0] === 'event-' . $this->code($id) . '@example.org' ? $events[0] : $events[1];
        $this->assertSame(['TZID=Europe/Madrid:20260302T090000'], $weekly['DTSTART'], 'Stored in UTC, written in local time.');
        $this->assertSame(['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20261231T225959Z'], $weekly['RRULE']);
        $this->assertSame(['TZID=Europe/Madrid:20260316T090000,20260401T090000'], $weekly['EXDATE']);
        $this->assertSame(['3'], $weekly['SEQUENCE']);
        $this->assertSame(['Reunió setmanal\; equip\, sala 2'], $weekly['SUMMARY']);

        $yearly = $events[0] === $weekly ? $events[1] : $events[0];
        $this->assertSame(['VALUE=DATE:20260623'], $yearly['DTSTART']);
        $this->assertSame(['VALUE=DATE:20260624'], $yearly['DTEND']);
        $this->assertSame(['FREQ=YEARLY;COUNT=5'], $yearly['RRULE']);
        $this->assertSame(['VALUE=DATE:20270624'], $yearly['EXDATE']);
    }

    public function testThePersonalFeedSecretIsShownOnceStoredHashedAndCanBeRegenerated(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->event('Reunió privada');

        $page = $this->send('POST', 'backend/calendar/subscription/generate');
        $page->assertOK();
        $this->assertSame(1, preg_match('#ical/user/([A-Za-z0-9_-]{43})\.ics#', html_entity_decode((string) $page->response()->getBody(), ENT_QUOTES | ENT_HTML5), $m));
        $first = $m[1] ?? '';

        $row = db_connect()->table('ical_tokens')->where('user_id', $user->id)->get()->getRowArray();
        $this->assertSame(hash('sha256', $first), $row['token_hash']);
        $this->assertStringNotContainsString($first, json_encode($row, JSON_THROW_ON_ERROR), 'Only the hash is stored.');
        $this->send('GET', 'backend/calendar/subscription')->assertDontSee($first);

        $feed = $this->feed('ical/user/' . $first . '.ics');
        $feed->assertOK();
        $this->assertStringStartsWith('text/calendar', $feed->response()->getHeaderLine('Content-Type'));
        $this->assertSame([], IcalValidator::validate((string) $feed->response()->getBody()));
        $this->assertStringContainsString('SUMMARY:Reunió privada', (string) $feed->response()->getBody());
        $this->assertNotNull(db_connect()->table('ical_tokens')->where('user_id', $user->id)->get()->getRowArray()['last_access_at']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'ical_accessed')->countAllResults());

        $second = $this->send('POST', 'backend/calendar/subscription/generate');
        $second->assertOK();
        $this->assertNotSame(hash('sha256', $first), db_connect()->table('ical_tokens')->where('user_id', $user->id)->get()->getRowArray()['token_hash']);
        $this->assertNotFound('GET', 'ical/user/' . $first . '.ics');

        $this->send('POST', 'backend/calendar/subscription/revoke')->assertRedirect();
        $this->assertNotNull(db_connect()->table('ical_tokens')->where('user_id', $user->id)->get()->getRowArray()['revoked_at']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'ical_created')->countAllResults());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'ical_regenerated')->countAllResults());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'ical_revoked')->countAllResults());
    }

    public function testGeneratingTheSecretNeedsRecentReauthentication(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        Time::setTestNow(Time::now()->addMinutes(60));

        $this->send('POST', 'backend/calendar/subscription/generate')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame(0, db_connect()->table('ical_tokens')->where('user_id', $user->id)->countAllResults());
        Time::setTestNow();
    }

    public function testTheFeedFollowsTheUsersCurrentPermissionsAndAudiences(): void
    {
        $user   = $this->createUser('edi@example.com', 'editor');
        $secret = (new IcalFeeds())->issue($user);
        $this->event('Per als editors', ['visibility' => 'restricted'], ['editor']);
        $this->event('Per a tothom');

        $this->assertSame(['Per als editors', 'Per a tothom'], $this->summaries('ical/user/' . $secret . '.ics'));

        $user->removeGroup('editor');
        $user->addGroup('user');
        $this->assertSame(['Per a tothom'], $this->summaries('ical/user/' . $secret . '.ics'), 'A user who loses access stops seeing the event.');

        $user->ban();
        $this->assertNotFound('GET', 'ical/user/' . $secret . '.ics');
    }

    public function testUnknownOrMalformedSecretsAreNotFound(): void
    {
        $this->assertNotFound('GET', 'ical/user/' . str_repeat('A', 43) . '.ics');
        $this->assertNotFound('GET', 'ical/user/short.ics');
        $this->assertNotFound('GET', 'ical/user/' . str_repeat('A', 43) . '.txt');
    }

    public function testEtagAndLastModifiedAnswerNotModified(): void
    {
        $user   = $this->createUser('ana@example.com', 'user');
        $secret = (new IcalFeeds())->issue($user);
        $id     = $this->event('Reunió');

        $first = $this->feed('ical/user/' . $secret . '.ics');
        $etag  = $first->response()->getHeaderLine('ETag');
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{40}"$/', $etag);
        $this->assertNotSame('', $first->response()->getHeaderLine('Last-Modified'));

        $again = $this->feed('ical/user/' . $secret . '.ics', ['If-None-Match' => $etag]);
        $again->assertStatus(304);
        $this->assertSame('', (string) $again->response()->getBody());

        db_connect()->table('events')->where('id', $id)->update(['location' => 'Sala 3', 'updated_at' => Time::now()->addMinutes(1)->toDateTimeString(), 'sequence' => 1]);
        $this->feed('ical/user/' . $secret . '.ics', ['If-None-Match' => $etag])->assertStatus(200);
    }

    public function testDeletedAndCancelledEventsArePublishedAsCancelledDuringTheGracePeriod(): void
    {
        $user   = $this->createUser('ana@example.com', 'user');
        $secret = (new IcalFeeds())->issue($user);
        $this->event('Anul·lada', ['status' => 'cancelled']);
        $this->event('Esborrada fa poc', ['deleted_at' => Time::now()->subDays(2)->toDateTimeString(), 'sequence' => 2]);
        $this->event('Esborrada fa temps', ['deleted_at' => Time::now()->subDays(40)->toDateTimeString()]);

        $ics    = (string) $this->feed('ical/user/' . $secret . '.ics')->response()->getBody();
        $events = [];

        foreach (IcalValidator::events($ics) as $event) {
            $events[$event['SUMMARY'][0]] = $event['STATUS'][0];
        }

        $this->assertSame(['Anul·lada' => 'CANCELLED', 'Esborrada fa poc' => 'CANCELLED'], $events);
    }

    public function testRemindersBecomeAlarmsAndFiltersApply(): void
    {
        $user   = $this->createUser('ana@example.com', 'user');
        $feeds  = new IcalFeeds();
        $secret = $feeds->issue($user);
        $db     = db_connect();
        $db->table('event_categories')->insert(['slug' => 'classes', 'color' => '#198754', 'position' => 1, 'active' => 1]);
        $category = (int) $db->insertID();
        $class    = $this->event('Classe', ['category_id' => $category]);
        $this->event('Altres');
        $db->table('event_reminders')->insert(['event_id' => $class, 'user_id' => $user->id, 'minutes_before' => 15]);

        $ics = (string) $this->feed('ical/user/' . $secret . '.ics')->response()->getBody();
        $this->assertSame([], IcalValidator::validate($ics));
        $this->assertStringContainsString("BEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:Classe\r\nTRIGGER:-PT15M\r\nEND:VALARM", $ics);

        $feeds->setFilters($user, [$category], []);
        $this->assertSame(['Classe'], $this->summaries('ical/user/' . $secret . '.ics'));
    }

    public function testPublicFeedsOnlyHavePublicEvents(): void
    {
        $db = db_connect();
        $db->table('event_categories')->insert(['slug' => 'festes', 'position' => 1, 'active' => 1]);
        $category = (int) $db->insertID();
        $this->event('Pública', ['visibility' => 'public']);
        $this->event('Festa major', ['visibility' => 'public', 'category_id' => $category]);
        $this->event('Privada');

        $all = $this->feed('ical/public/all.ics');
        $all->assertOK();
        $this->assertSame([], IcalValidator::validate((string) $all->response()->getBody()));
        $this->assertSame(['Pública', 'Festa major'], $this->summaries('ical/public/all.ics'));
        $this->assertSame(['Festa major'], $this->summaries('ical/public/festes.ics'));
        $this->assertNotFound('GET', 'ical/public/unknown.ics');

        config(Calendar::class)->publicFeeds = false;
        $this->assertNotFound('GET', 'ical/public/all.ics');
        config(Calendar::class)->publicFeeds = true;
    }

    public function testOneOffExportsRespectVisibility(): void
    {
        $public  = $this->event('Concert', ['visibility' => 'public']);
        $private = $this->event('Claustre');

        $response = $this->feed('calendar/event/' . $this->code($public) . '/export.ics');
        $response->assertOK();
        $this->assertStringContainsString('attachment; filename="concert.ics"', $response->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame([], IcalValidator::validate((string) $response->response()->getBody()));
        $this->assertNotFound('GET', 'calendar/event/' . $this->code($private) . '/export.ics');

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->send('GET', 'backend/calendar/event/' . $this->code($private) . '/export.ics')->assertOK();
        $only = $this->event('Només per a un altre', ['visibility' => 'restricted'], ['admin']);
        $this->assertNotFound('GET', 'backend/calendar/event/' . $this->code($only) . '/export.ics');
    }

    public function testTheSubscriptionPageHasTheInstructionsAndNoInlineCode(): void
    {
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $body = $this->body('POST', 'backend/calendar/subscription/generate');
        $this->assertStringContainsString('webcal://', $body);
        $this->assertStringContainsString('Synology Calendar', $body);
        $this->assertStringContainsString('data-component="events/copy"', $body);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="|\sstyle="/i', $body);
    }

    /**
     * A request without session (calendar clients).
     *
     * @param array<string, string> $headers
     */
    private function feed(string $path, array $headers = []): TestResponse
    {
        $session = $_SESSION ?? [];
        Services::resetSingle('response');
        Services::resetSingle('auth');

        $response = $this->withSession([])->withHeaders($headers)->call('GET', $path);
        $this->withHeaders([]);
        $_SESSION = $session;

        return $response;
    }

    /**
     * @return list<string>
     */
    private function summaries(string $path): array
    {
        $ics = (string) $this->feed($path)->response()->getBody();
        $this->assertSame([], IcalValidator::validate($ics), $path);

        return array_map(static fn (array $event): string => str_replace('\\', '', $event['SUMMARY'][0]), IcalValidator::events($ics));
    }
}
