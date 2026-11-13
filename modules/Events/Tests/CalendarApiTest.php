<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\StreamFilterTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\NullCalendar;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Events\Config\Calendar;
use Modules\Events\Services\Calendars;
use Modules\Events\Services\CalendarService;
use Modules\Events\Services\EventVisibility;
use Modules\Events\Tests\Support\DeadlinesProvider;
use Modules\Events\Tests\Support\EnrolledPolicy;
use Modules\Events\Tests\Support\LessonsSource;

/**
 * The calendar API (docs/calendar-api.md): publish/unpublish (idempotent, SEQUENCE, audiences,
 * locked in the administration), calendars with permissions and access policies, providers
 * filtered by user, disabled modules, calendar toggles, resync, purge and the CodeIgniter events.
 *
 * A fake module "Courses" declares its calendars and provider in `module_declarations`.
 *
 * @internal
 */
final class CalendarApiTest extends EventsTestCase
{
    use StreamFilterTrait;

    protected function setUp(): void
    {
        parent::setUp();
        config(Calendar::class)->icalCacheSeconds     = 0;
        config(Calendar::class)->providerCacheSeconds = 0;
        EnrolledPolicy::$enrolled = [];
        LessonsSource::$lessons   = [];
        DeadlinesProvider::$calls = 0;

        $db = db_connect();

        foreach ([
            ['calendar', 'lessons', ['label' => 'Lessons', 'color' => '#0d6efd', 'policy' => EnrolledPolicy::class, 'source' => LessonsSource::class]],
            ['calendar', 'deadlines', ['label' => 'Deadlines', 'color' => '#fd7e14']],
            ['calendar', 'staff', ['label' => 'Staff', 'color' => '#6c757d', 'permission' => 'events-events.all']],
            ['calendarProvider', DeadlinesProvider::class, []],
        ] as [$kind, $key, $definition]) {
            $db->table('module_declarations')->insert(['module' => 'Courses', 'kind' => $kind, 'key' => $key, 'definition' => json_encode($definition)]);
        }

        (new CacheInvalidator())->invalidate();
    }

    public function testTheServiceIsTheEventsCalendar(): void
    {
        $this->assertInstanceOf(CalendarService::class, service('calendar'));
        $this->assertFalse((new NullCalendar())->publish('Courses', 'x', $this->lesson()));
    }

    public function testPublishingIsIdempotentAndChangesBumpTheSequence(): void
    {
        $service = new CalendarService();

        $this->assertTrue($service->publish('Courses', 'session-1', $this->lesson()));
        $row = $this->published('session-1');
        $this->assertSame('courses.lessons', $row['calendar']);
        $this->assertSame('/backend/courses/1', $row['source_url']);
        $this->assertSame(0, (int) $row['sequence']);
        $this->assertSame('Classe 1', db_connect()->table('event_translations')->where('event_id', $row['id'])->where('locale', 'ca')->get()->getRowArray()['title']);
        $this->assertSame('Class 1', db_connect()->table('event_translations')->where('event_id', $row['id'])->where('locale', 'en')->get()->getRowArray()['title']);

        $this->assertFalse($service->publish('Courses', 'session-1', $this->lesson()), 'The same data changes nothing.');
        $this->assertSame(0, (int) $this->published('session-1')['sequence']);
        $this->assertSame(1, db_connect()->table('events')->where('source_key', 'session-1')->countAllResults());

        $this->assertTrue($service->publish('Courses', 'session-1', $this->lesson(location: 'Aula 5')));
        $this->assertSame(1, (int) $this->published('session-1')['sequence']);

        $this->assertTrue($service->unpublish('Courses', 'session-1'));
        $this->assertFalse($service->unpublish('Courses', 'session-1'), 'Unpublishing twice changes nothing.');
        $this->assertNotNull($this->published('session-1')['deleted_at']);
        $this->assertSame(2, (int) $this->published('session-1')['sequence']);

        $this->assertTrue($service->publish('Courses', 'session-1', $this->lesson(location: 'Aula 5')), 'An unpublished event comes back.');
        $this->assertNull($this->published('session-1')['deleted_at']);
        $this->assertSame(3, (int) $this->published('session-1')['sequence']);
        $this->assertFalse($service->unpublish('Courses', 'unknown'));
    }

    public function testRestrictedAudiencesCombineGroupsAndUsers(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $ana  = $this->createUser('ana@example.com', 'user');
        $edi  = $this->createUser('edi@example.com', 'editor');
        (new CalendarService())->publish('Courses', 'meeting', new CalendarEvent(
            title: 'Reunió',
            start: new DateTimeImmutable('tomorrow 10:00'),
            calendar: 'courses.deadlines',
            visibility: 'restricted',
            groups: ['editor'],
            users: [(int) $john->id, 99999],
        ));
        $id = (int) $this->published('meeting')['id'];

        $this->assertTrue(EventVisibility::canSee($id, $john));
        $this->assertTrue(EventVisibility::canSee($id, $edi));
        $this->assertFalse(EventVisibility::canSee($id, $ana));
        $this->assertFalse(EventVisibility::canSee($id, null));
        $this->assertSame(2, db_connect()->table('event_audience')->where('event_id', $id)->countAllResults(), 'Unknown users are dropped.');

        $this->expectException(InvalidArgumentException::class);
        (new CalendarService())->publish('Courses', 'other', new CalendarEvent(title: 'X', start: new DateTimeImmutable(), visibility: 'restricted', groups: ['nope']));
    }

    public function testAModuleCannotPublishToAnotherModulesCalendar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalendarService())->publish('Courses', 'intruder', new CalendarEvent(title: 'X', start: new DateTimeImmutable(), calendar: 'news.notices'));
    }

    public function testPublishedEventsAreLockedInTheAdministration(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        (new CalendarService())->publish('Courses', 'session-1', $this->lesson());
        $row  = $this->published('session-1');
        $code = $this->code((int) $row['id']);

        $list = $this->body('GET', 'backend/events');
        $this->assertStringContainsString('Managed by Courses', $list);
        $this->assertStringContainsString('Edit in Courses', $list);

        $this->assertCrudRefused($this->send('POST', 'backend/events/edit/' . $code, ['kp_tr' => ['ca' => ['title' => 'Canviat']], 'starts_at' => '2030-01-01 10:00:00', 'visibility' => 'public', 'status' => 'confirmed']), 'backend/events');
        $this->assertCrudRefused($this->send('POST', 'backend/events/delete/' . $code), 'backend/events');
        $this->assertSame('Classe 1', db_connect()->table('event_translations')->where('event_id', $row['id'])->where('locale', 'ca')->get()->getRowArray()['title']);
        $this->assertNull($this->published('session-1')['deleted_at']);
    }

    public function testCalendarPermissionsAndAccessPoliciesNarrowWhatUsersSee(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $ana  = $this->createUser('ana@example.com', 'user');
        $boss = $this->createUser('boss@example.com', 'admin');
        $service = new CalendarService();
        $service->publish('Courses', 'lesson-1', $this->lesson());
        $service->publish('Courses', 'staff-1', new CalendarEvent(title: 'Claustre', start: new DateTimeImmutable('tomorrow 16:00'), calendar: 'courses.staff'));
        EnrolledPolicy::$enrolled = ['lesson-1' => [(int) $john->id]];

        $lesson = (int) $this->published('lesson-1')['id'];
        $staff  = (int) $this->published('staff-1')['id'];

        $this->assertTrue(EventVisibility::canSee($lesson, $john), 'Enrolled.');
        $this->assertFalse(EventVisibility::canSee($lesson, $ana), 'The policy refuses users who are not enrolled.');
        $this->assertFalse(EventVisibility::canSee($lesson, $boss), 'Admins too.');
        $this->assertFalse(EventVisibility::canSee($staff, $john), 'The calendar needs events-events.all.');
        $this->assertTrue(EventVisibility::canSee($staff, $boss));

        $this->login('john@example.com');
        $titles = $this->feedTitles();
        $this->assertContains('Class 1', $titles, 'Texts in the user\'s language (en).');
        $this->assertNotContains('Claustre', $titles);
        $this->assertStringContainsString('Class 1', $this->body('GET', 'backend/calendar/event/' . $this->code($lesson)));
        $this->send('POST', 'logout');

        $this->login('ana@example.com');
        $this->assertNotContains('Class 1', $this->feedTitles());
        $this->assertNotFound('GET', 'backend/calendar/event/' . $this->code($lesson));
    }

    public function testEventsAndProvidersOfADisabledModuleAreHidden(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        (new CalendarService())->publish('Courses', 'lesson-1', $this->lesson(policyFree: true));
        $this->login('john@example.com');

        $this->assertContains('Class 1', $this->feedTitles());
        $this->assertContains('Lliurament de la pràctica', $this->feedTitles());

        db_connect()->table('modules')->insert(['name' => 'Courses', 'version' => '1.0.0', 'enabled' => 0]);
        (new CacheInvalidator())->invalidate();

        $titles = $this->feedTitles();
        $this->assertNotContains('Class 1', $titles);
        $this->assertNotContains('Lliurament de la pràctica', $titles);
        $this->assertFalse(EventVisibility::canSee((int) $this->published('lesson-1')['id'], $john));
    }

    public function testProvidersReturnEventsPerUserOnlyForTheirOwnCalendars(): void
    {
        $this->createUser('first@example.com', 'user');
        $this->createUser('ana@example.com', 'user');

        $this->login('first@example.com');
        $titles = $this->feedTitles();
        $this->assertContains('Lliurament de la pràctica', $titles);
        $this->assertContains('Tutoria personal', $titles);
        $this->assertNotContains('Intrús', $titles, 'A provider cannot fill another module\'s calendar.');
        $this->send('POST', 'logout');

        $this->login('ana@example.com');
        $titles = $this->feedTitles();
        $this->assertContains('Lliurament de la pràctica', $titles);
        $this->assertNotContains('Tutoria personal', $titles);

        $this->assertNotContains('Lliurament de la pràctica', $this->feedTitles('calendar/feed'), 'Never on the public site.');
    }

    public function testProviderResultsAreCached(): void
    {
        config(Calendar::class)->providerCacheSeconds = 300;
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->feedTitles();
        $this->feedTitles();
        $this->assertSame(1, DeadlinesProvider::$calls);
    }

    public function testUsersToggleTheCalendarsTheySee(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->event('Propi');
        (new CalendarService())->publish('Courses', 'deadline', new CalendarEvent(title: 'Publicat', start: new DateTimeImmutable('tomorrow 11:00'), calendar: 'courses.deadlines'));

        $this->assertSame(['Lliurament de la pràctica', 'Propi', 'Publicat'], $this->sorted($this->feedTitles()));
        $page = $this->body('GET', 'backend/calendar');
        $this->assertStringContainsString('value="courses.deadlines"', $page);
        $this->assertStringNotContainsString('value="courses.staff"', $page, 'Calendars the user may not see are not offered.');

        $this->send('POST', 'backend/calendar/calendars', ['calendars' => ['events', 'courses.lessons', 'courses.staff']])->assertRedirect();
        $hidden = (new Calendars())->hidden($user);
        $this->assertContains('courses.deadlines', $hidden);
        $this->assertNotContains('events', $hidden);
        $this->assertNotContains('courses.staff', $hidden, 'Only calendars the user can see are stored.');
        $this->assertSame(['Propi'], $this->feedTitles());
    }

    public function testResyncPublishesMissingEventsAndRemovesStaleOnes(): void
    {
        $service = new CalendarService();
        $service->publish('Courses', 'old', new CalendarEvent(title: 'Antiga', start: new DateTimeImmutable('tomorrow 09:00'), calendar: 'courses.lessons'));
        $service->publish('Courses', 'other-calendar', new CalendarEvent(title: 'Una altra', start: new DateTimeImmutable('tomorrow 09:00'), calendar: 'courses.deadlines'));
        LessonsSource::$lessons = ['l1' => 'Lliçó 1', 'l2' => 'Lliçó 2'];

        $this->assertSame(['published' => 2, 'unchanged' => 0, 'unpublished' => 1, 'calendars' => 1], $service->resync('Courses'));
        $this->assertNotNull($this->published('old')['deleted_at']);
        $this->assertNull($this->published('other-calendar')['deleted_at'], 'Calendars without a source are left alone.');
        $this->assertSame(['published' => 0, 'unchanged' => 2, 'unpublished' => 0, 'calendars' => 1], $service->resync('Courses'));

        $this->assertStringContainsString('Courses: 0 event(s) published or updated, 2 unchanged, 0 removed.', $this->runCommand('calendar:resync Courses'));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'calendar_resync')->countAllResults());
        $this->assertSame(['published' => 0, 'unchanged' => 0, 'unpublished' => 0, 'calendars' => 0], $service->resync('Nothing'));
    }

    public function testPurgeDeletesEveryEventOfAModuleAndEventsAreFired(): void
    {
        $fired = [];

        foreach ([CalendarService::EVENT_CREATED, CalendarService::EVENT_UPDATED, CalendarService::EVENT_DELETED] as $name) {
            Events::on($name, static function (array $data) use (&$fired, $name): void {
                $fired[] = $name . ':' . ($data['key'] ?? '');
            });
        }

        $service = new CalendarService();
        $service->publish('Courses', 'a', $this->lesson());
        $service->publish('Courses', 'a', $this->lesson(location: 'Aula 9'));
        $service->unpublish('Courses', 'a');
        $service->publish('Courses', 'b', $this->lesson());

        $this->assertSame(['calendar.created:a', 'calendar.updated:a', 'calendar.deleted:a', 'calendar.created:b'], $fired);
        $this->assertSame(2, $service->purgeModule('Courses'));
        $this->assertSame(0, db_connect()->table('events')->where('source_module', 'Courses')->countAllResults());
    }

    public function testInvalidEventsAreRefused(): void
    {
        foreach ([
            static fn () => new CalendarEvent(title: '', start: new DateTimeImmutable()),
            static fn () => new CalendarEvent(title: 'X', start: new DateTimeImmutable(), visibility: 'secret'),
            static fn () => new CalendarEvent(title: 'X', start: new DateTimeImmutable('tomorrow'), end: new DateTimeImmutable('today')),
            static fn () => new CalendarEvent(title: 'X', start: new DateTimeImmutable(), url: 'javascript:alert(1)'),
            static fn () => new CalendarEvent(title: 'X', start: new DateTimeImmutable(), calendar: 'Bad Calendar'),
            static fn () => (new CalendarService())->publish('courses', 'k', new CalendarEvent(title: 'X', start: new DateTimeImmutable())),
            static fn () => (new CalendarService())->publish('Courses', 'bad key!', new CalendarEvent(title: 'X', start: new DateTimeImmutable())),
        ] as $i => $build) {
            try {
                $build();
                $this->fail("Case {$i} must be refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function runCommand(string $command): string
    {
        command($command);

        return $this->getStreamFilterBuffer();
    }

    private function lesson(string $location = 'Aula 1', bool $policyFree = false): CalendarEvent
    {
        return new CalendarEvent(
            title: ['ca' => 'Classe 1', 'en' => 'Class 1'],
            start: new DateTimeImmutable('tomorrow 09:00'),
            end: new DateTimeImmutable('tomorrow 10:00'),
            location: $location,
            description: '<p>Unitat 1</p><script>alert(1)</script>',
            calendar: $policyFree ? 'courses.deadlines' : 'courses.lessons',
            url: '/backend/courses/1',
            reminders: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function published(string $key): array
    {
        $row = db_connect()->table('events')->where('source_module', 'Courses')->where('source_key', $key)->get()->getRowArray();
        $this->assertIsArray($row, $key);

        return $row;
    }

    /**
     * @param list<string> $titles
     *
     * @return list<string>
     */
    private function sorted(array $titles): array
    {
        sort($titles);

        return $titles;
    }
}
