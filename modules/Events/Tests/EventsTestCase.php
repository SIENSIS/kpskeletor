<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Dashboard\Tests\DashboardTestCase;
use Modules\Events\Services\EventRepository;

/**
 * Base of the Events feature tests: the Auth fixture, every manifest's declarations (the Events
 * widget included), the permission catalogue and default grants, and helpers that write events
 * directly (`event()`), mask ids and read the calendar feeds.
 */
abstract class EventsTestCase extends DashboardTestCase
{
    protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'Events'];

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    /**
     * Inserts an event; defaults: a private, confirmed, one-hour event tomorrow at 10:00 (UTC),
     * Catalan title.
     *
     * @param array<string, mixed> $values
     * @param list<string>         $groups group names of the audience
     * @param list<int>            $users  user ids of the audience
     */
    protected function event(string $title, array $values = [], array $groups = [], array $users = []): int
    {
        $now      = Time::now();
        $tomorrow = Time::tomorrow()->setHour(10);
        $db       = db_connect();

        $db->table('events')->insert($values + [
            'starts_at'  => $tomorrow->toDateTimeString(),
            'ends_at'    => $tomorrow->addHours(1)->toDateTimeString(),
            'all_day'    => 0,
            'visibility' => 'private',
            'status'     => 'confirmed',
            'sequence'   => 0,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);
        $id = (int) $db->insertID();

        $db->table('event_translations')->insert(['event_id' => $id, 'locale' => 'ca', 'title' => $title, 'description' => '<p>Text de ' . $title . '</p>']);

        foreach ($groups as $group) {
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => $this->groupId($group), 'user_id' => null]);
        }

        foreach ($users as $user) {
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => null, 'user_id' => $user]);
        }

        return $id;
    }

    protected function groupId(string $name): int
    {
        $row = db_connect()->table('groups')->where('name', $name)->get()->getRowArray();
        $this->assertIsArray($row, "Group {$name} exists.");

        return (int) $row['id'];
    }

    protected function code(int $id): string
    {
        return EventRepository::code($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(int $id): array
    {
        $row = db_connect()->table('events')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * The titles in a calendar feed for the next days (back office or public).
     *
     * @return list<string>
     */
    protected function feedTitles(string $path = 'backend/calendar/feed', int $days = 30, string $extra = ''): array
    {
        $start    = date('Y-m-d', time() - 86400);
        $end      = date('Y-m-d', time() + $days * 86400);
        $response = $this->send('GET', $path . '?start=' . $start . '&end=' . $end . $extra);
        $response->assertOK();
        $data = json_decode((string) $response->response()->getBody(), true);
        $this->assertIsArray($data);

        return array_values(array_unique(array_map(static fn (array $event): string => (string) $event['title'], $data['events'])));
    }

    /**
     * The page as text with its HTML entities decoded.
     *
     * @param array<string, mixed> $params
     */
    protected function body(string $method, string $path, array $params = []): string
    {
        return html_entity_decode((string) $this->send($method, $path, $params)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
