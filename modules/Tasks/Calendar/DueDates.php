<?php

declare(strict_types=1);

namespace Modules\Tasks\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Kpcrudy\Crud;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarPublisher;
use Modules\Core\Calendar\CalendarSource;

/**
 * Calendar `tasks.due` (tutorial 6, **push**): the due date of every open to-do task, an all-day
 * event seen only by its assignees and its creator (`restricted`, user ids). A done or trashed
 * to-do task, or one without a due date, has no event.
 *
 * `sync()` runs after every change of a to-do task (CRUD `afterWrite`, the `complete` operation
 * and bulk action, the board, the API); `all()` lets `php spark calendar:resync Tasks` repair drift.
 * With the Events module off, `service('calendar')` is a null publisher and nothing happens.
 */
final class DueDates implements CalendarSource
{
    public const MODULE   = 'Tasks';
    public const CALENDAR = 'tasks.due';

    /**
     * Publishes or unpublishes the event of one to-do task. Returns whether the calendar changed.
     */
    public static function sync(int $id): bool
    {
        $row     = db_connect()->table('tasks')->where('id', $id)->get(1)->getRowArray();
        $event   = is_array($row) ? self::event($row) : null;
        $service = self::calendar();

        return $event === null ? $service->unpublish(self::MODULE, self::key($id)) : $service->publish(self::MODULE, self::key($id), $event);
    }

    /**
     * @param list<int|string> $ids
     */
    public static function syncMany(array $ids): void
    {
        foreach ($ids as $id) {
            self::sync((int) $id);
        }
    }

    public static function key(int $id): string
    {
        return 'task-' . $id;
    }

    /**
     * @return iterable<string, CalendarEvent>
     */
    public function all(): iterable
    {
        $rows = db_connect()->table('tasks')->where('status !=', 'done')->where('deleted_at', null)
            ->where('due_on IS NOT NULL', null, false)->get()->getResultArray();

        foreach ($rows as $row) {
            $event = self::event($row);

            if ($event !== null) {
                yield self::key((int) $row['id']) => $event;
            }
        }
    }

    /**
     * The calendar event of a to-do task, or null when it must not be in the calendar.
     *
     * @param array<string, mixed> $row a `tasks` row
     */
    public static function event(array $row): ?CalendarEvent
    {
        $due = $row['due_on'] ?? null;

        if (($row['status'] ?? '') === 'done' || ($row['deleted_at'] ?? null) !== null || ! is_string($due) || preg_match('/^\d{4}-\d{2}-\d{2}/', $due) !== 1) {
            return null;
        }

        $id    = (int) $row['id'];
        $users = array_map(static fn (array $link): int => (int) $link['user_id'], db_connect()->table('task_assignees')->select('user_id')->where('task_id', $id)->get()->getResultArray());

        if (is_numeric($row['created_by'] ?? null)) {
            $users[] = (int) $row['created_by'];
        }

        $users = array_values(array_unique($users));
        sort($users);

        if ($users === []) {
            return null;
        }

        $zone = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');

        return new CalendarEvent(
            title: CalendarEvent::translated('Tasks.cal.dueTitle', [(string) $row['title']]),
            start: new DateTimeImmutable(substr($due, 0, 10) . ' 00:00:00', $zone),
            allDay: true,
            calendar: self::CALENDAR,
            visibility: 'restricted',
            users: $users,
            url: '/' . backend_link('tasks/tasks/show/' . Crud::maskId($id, 'tasks')),
            reminders: true,
        );
    }

    /**
     * Whether the user is in the audience of a to-do task's event (used by tests and the API).
     */
    public static function reaches(User $user, int $id): bool
    {
        $row   = db_connect()->table('tasks')->where('id', $id)->get(1)->getRowArray();
        $event = is_array($row) ? self::event($row) : null;

        return $event !== null && in_array((int) $user->id, $event->users, true);
    }

    private static function calendar(): CalendarPublisher
    {
        /** @var CalendarPublisher */
        return service('calendar');
    }
}
