<?php

declare(strict_types=1);

namespace Modules\Tasks\Calendar;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarProvider;
use Modules\Core\Permissions\Authorization;
use Modules\Tasks\Services\TaskRepository;

/**
 * Calendar `tasks.projects` (tutorial 6, **pull**): the deadline (`ends_on`) of each active
 * project, computed when the calendar asks for a range; nothing is stored. Only for users who
 * may list the projects (`tasks-projects.list`, also the calendar's `permission`).
 */
final class ProjectDeadlines implements CalendarProvider
{
    public const CALENDAR = 'tasks.projects';

    public function calendar(): string
    {
        return self::CALENDAR;
    }

    /**
     * @return list<CalendarEvent>
     */
    public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
    {
        if (! Authorization::for($user)->can('tasks-projects.list')) {
            return [];
        }

        $zone  = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');
        $start = DateTimeImmutable::createFromInterface($from)->setTimezone($zone)->format('Y-m-d');
        $end   = DateTimeImmutable::createFromInterface($to)->setTimezone($zone)->format('Y-m-d');
        $rows  = db_connect()->table('task_projects')->select('id, slug, color, ends_on')
            ->where('active', 1)->where('ends_on >=', $start)->where('ends_on <', $end)
            ->orderBy('ends_on')->get()->getResultArray();

        $names  = (new TaskRepository())->projectNames(true);
        $events = [];

        foreach ($rows as $row) {
            $name     = $names[(int) $row['id']] ?? (string) $row['slug'];
            $events[] = new CalendarEvent(
                title: lang('Tasks.cal.projectTitle', [$name]),
                start: new DateTimeImmutable((string) $row['ends_on'] . ' 00:00:00', $zone),
                allDay: true,
                calendar: self::CALENDAR,
                visibility: 'private',
                color: preg_match('/^#[0-9a-fA-F]{6}$/', (string) $row['color']) === 1 ? (string) $row['color'] : null,
                key: 'project-' . (int) $row['id'],
            );
        }

        return $events;
    }
}
