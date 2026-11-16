<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Events\Services\EventRepository;

/**
 * API resources `events` and `public/events` (D-26-01): the calendar's visibility rules
 * (public, private, restricted audiences), occurrences of a range with recurrences and
 * calendars, the calendars list, scopes, masked ids shared with the back office, public
 * events without a token and their rate limit.
 *
 * @internal
 */
final class EventsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    public function testTheListRespectsRestrictedAudiences(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $joan = $this->createUser('joan@example.com', 'user');
        $this->event('Pública', ['visibility' => 'public']);
        $this->event('Privada');
        $this->event('Per als editors', ['visibility' => 'restricted'], ['editor']);
        $this->event('Només l’Ana', ['visibility' => 'restricted'], [], [(int) $ana->id]);
        $this->event('Esborrada', ['deleted_at' => Time::now()->toDateTimeString()]);

        $this->assertSame(['Només l’Ana', 'Privada', 'Pública'], $this->titles('api/v1/events', $this->tokenFor($ana)));
        $this->assertSame(['Privada', 'Pública'], $this->titles('api/v1/events', $this->tokenFor($joan)));
    }

    public function testAnAdministratorFollowsTheSameRule(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $admin = $this->createUser('admin@example.com', 'admin');
        $id    = $this->event('Només l’Ana', ['visibility' => 'restricted'], [], [(int) $ana->id]);

        $this->assertSame([], $this->titles('api/v1/events', $this->tokenFor($admin)));
        $this->api('GET', 'api/v1/events/' . EventRepository::code($id), null, $this->tokenFor($admin))->assertStatus(404);
    }

    public function testDetailsUseTheBackOfficeIdAndTheRecurrenceRule(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $id  = $this->event('Reunió', ['recur_freq' => 'weekly', 'recur_days' => 'tu,th', 'recur_count' => 6, 'recur_exceptions' => '2030-01-01']);

        $response = $this->api('GET', 'api/v1/events/' . EventRepository::code($id), null, $this->tokenFor($ana));
        $response->assertStatus(200);
        $data = $this->json($response)['data'];

        $this->assertSame(EventRepository::code($id), $data['id']);
        $this->assertSame('Reunió', $data['title']);
        $this->assertSame('<p>Text de Reunió</p>', $data['description']);
        $this->assertSame('events', $data['calendar']);
        $this->assertFalse($data['all_day']);
        $this->assertSame(['freq' => 'weekly', 'interval' => 1, 'days' => ['tu', 'th'], 'until' => null, 'count' => 6, 'exceptions' => ['2030-01-01']], $data['recurrence']);
    }

    public function testOccurrencesOfARangeExpandRecurrences(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $start = Time::tomorrow()->setHour(10);
        $this->event('Diària', ['recur_freq' => 'daily', 'recur_count' => 3]);
        $this->event('Amagada', ['visibility' => 'restricted'], ['editor']);
        $token = $this->tokenFor($ana);
        $range = '?start=' . $start->toDateString() . '&end=' . $start->addDays(7)->toDateString();

        $response = $this->api('GET', 'api/v1/events/occurrences' . $range, null, $token);
        $response->assertStatus(200);
        $items = $this->json($response)['data'];

        $this->assertSame(['Diària', 'Diària', 'Diària'], array_column($items, 'title'));
        $this->assertSame($start->toDateString(), substr((string) $items[0]['start'], 0, 10));
        $this->assertTrue($items[0]['recurring']);
        $this->assertSame('events', $items[0]['calendar']);
        $this->assertCount(3, array_unique(array_column($items, 'id')));

        // Only the calendars asked for.
        $this->assertSame([], $this->json($this->api('GET', 'api/v1/events/occurrences' . $range . '&calendars=news.notices', null, $token))['data']);

        // A missing or too long range is refused.
        $this->api('GET', 'api/v1/events/occurrences', null, $token)->assertStatus(422);
        $this->api('GET', 'api/v1/events/occurrences?start=2026-01-01&end=2030-01-01', null, $token)->assertStatus(422);
    }

    public function testCalendarsListsTheCalendarsTheUserMaySee(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');

        $response = $this->api('GET', 'api/v1/events/calendars', null, $this->tokenFor($ana));
        $response->assertStatus(200);
        $ids = array_column($this->json($response)['data'], 'id');

        $this->assertContains('events', $ids);
        // Users' administrative birthdays calendar needs `users-users.birthdate`.
        $this->assertNotContains('users.birthdaysadmin', $ids);
    }

    public function testScopesNarrowTheToken(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $id  = $this->event('Privada');

        $this->api('GET', 'api/v1/events', null, $this->tokenFor($ana, ['events-api.list']))->assertStatus(200);
        $this->api('GET', 'api/v1/events/' . EventRepository::code($id), null, $this->tokenFor($ana, ['events-api.list']))->assertStatus(403);
        $this->api('GET', 'api/v1/events', null, $this->tokenFor($ana, ['events-calendar.view']))->assertStatus(403);
        $this->api('GET', 'api/v1/events')->assertStatus(401);
    }

    public function testItIsReadOnly(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $id    = $this->event('Privada');
        $token = $this->tokenFor($admin);

        $this->assertContains($this->api('POST', 'api/v1/events', ['title' => 'Nou'], $token)->response()->getStatusCode(), [403, 405]);
        $this->assertContains($this->api('DELETE', 'api/v1/events/' . EventRepository::code($id), null, $token)->response()->getStatusCode(), [403, 405]);
        $this->assertSame(1, db_connect()->table('events')->where('deleted_at', null)->countAllResults());
    }

    public function testPublicEventsWithoutAToken(): void
    {
        $public  = $this->event('Pública', ['visibility' => 'public']);
        $private = $this->event('Privada');
        $start   = Time::tomorrow();

        $this->assertSame(['Pública'], $this->titles('api/v1/public/events'));
        $this->api('GET', 'api/v1/public/events/' . EventRepository::code($public))->assertStatus(200);
        $this->api('GET', 'api/v1/public/events/' . EventRepository::code($private))->assertStatus(404);

        $occurrences = $this->json($this->api('GET', 'api/v1/public/events/occurrences?start=' . $start->toDateString() . '&end=' . $start->addDays(3)->toDateString()))['data'];
        $this->assertSame(['Pública'], array_column($occurrences, 'title'));

        try {
            $status = $this->api('POST', 'api/v1/public/events', ['title' => 'Nou'])->response()->getStatusCode();
        } catch (PageNotFoundException) {
            $status = 404;
        }

        $this->assertSame(404, $status);
    }

    public function testPublicEventsAreLimitedPerIpAndFollowThePublicCalendarSwitch(): void
    {
        $this->event('Pública', ['visibility' => 'public']);
        config('Kpapy')->rateLimitPublic = 2;

        $this->api('GET', 'api/v1/public/events')->assertStatus(200);
        config(\Modules\Events\Config\Calendar::class)->publicCalendar = false;
        $this->api('GET', 'api/v1/public/events')->assertStatus(404);
        $this->api('GET', 'api/v1/public/events')->assertStatus(429);
    }

    /**
     * @return list<string>
     */
    private function titles(string $path, ?string $token = null): array
    {
        $response = $this->api('GET', $path, null, $token);
        $response->assertStatus(200);
        $titles = array_column($this->json($response)['data'], 'title');
        sort($titles);

        return $titles;
    }

    /**
     * An event tomorrow at 10:00 (one hour, private, confirmed) with a Catalan title.
     *
     * @param array<string, mixed> $values
     * @param list<string>         $groups
     * @param list<int>            $users
     */
    private function event(string $title, array $values = [], array $groups = [], array $users = []): int
    {
        $start = Time::tomorrow()->setHour(10);
        $db    = db_connect();
        $db->table('events')->insert($values + [
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->addHours(1)->toDateTimeString(), 'all_day' => 0,
            'visibility' => 'private', 'status' => 'confirmed', 'sequence' => 0,
            'created_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString(),
        ]);
        $id = (int) $db->insertID();
        $db->table('event_translations')->insert(['event_id' => $id, 'locale' => 'ca', 'title' => $title, 'description' => '<p>Text de ' . $title . '</p>']);

        foreach ($groups as $group) {
            $row = $db->table('groups')->where('name', $group)->get()->getRowArray();
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => (int) ($row['id'] ?? 0), 'user_id' => null]);
        }

        foreach ($users as $user) {
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => null, 'user_id' => $user]);
        }

        return $id;
    }
}
