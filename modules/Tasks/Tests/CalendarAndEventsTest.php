<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use CodeIgniter\Events\Events;
use DateTimeImmutable;
use Modules\Tasks\Calendar\DueDates;
use Modules\Tasks\Calendar\ProjectDeadlines;

/**
 * Talking to other modules (tutorial 6): due dates pushed to the calendar `tasks.due` for the
 * assignees and the creator, project deadlines offered by a provider (pull) with its permission,
 * `calendar:resync` through `DueDates::all()`, and the listener of the Core's `privilegesChanged`.
 *
 * @internal
 */
final class CalendarAndEventsTest extends TasksTestCase
{
    public function testDueDatesArePublishedToTheAssigneesAndTheCreator(): void
    {
        $pau  = $this->createUser('pau@example.com', 'user');
        $boss = $this->createUser('boss@example.com', 'admin');
        $id   = $this->task('Amb data', ['due_on' => date('Y-m-d', strtotime('+2 days')), 'created_by' => $boss->id], [(int) $pau->id]);

        $this->assertTrue(DueDates::sync($id));
        $this->assertFalse(DueDates::sync($id), 'Idempotent.');

        $event = $this->event($id);
        $this->assertSame('tasks.due', $event['calendar']);
        $this->assertSame('restricted', $event['visibility']);
        $this->assertSame(1, (int) $event['all_day']);
        $this->assertSame('/backend/tasks/tasks/show/' . $this->taskCode($id), $event['source_url']);

        $users = array_map('intval', array_column(db_connect()->table('event_audience')->where('event_id', $event['id'])->where('user_id IS NOT NULL')->get()->getResultArray(), 'user_id'));
        sort($users);
        $expected = [(int) $pau->id, (int) $boss->id];
        sort($expected);
        $this->assertSame($expected, $users);
        $this->assertTrue(DueDates::reaches($pau, $id));
        $this->assertFalse(DueDates::reaches($this->createUser('joan@example.com', 'user'), $id));
    }

    public function testDoneTrashedOrUndatedToDoTasksLeaveTheCalendar(): void
    {
        $pau = $this->createUser('pau@example.com', 'user');
        $id  = $this->task('Amb data', ['due_on' => date('Y-m-d', strtotime('+2 days'))], [(int) $pau->id]);
        DueDates::sync($id);

        foreach ([['status' => 'done'], ['deleted_at' => date('Y-m-d H:i:s')], ['due_on' => null]] as $change) {
            db_connect()->table('tasks')->where('id', $id)->update(['status' => 'todo', 'deleted_at' => null, 'due_on' => date('Y-m-d', strtotime('+2 days'))]);
            DueDates::sync($id);
            $this->assertNull($this->event($id)['deleted_at']);

            db_connect()->table('tasks')->where('id', $id)->update($change);
            DueDates::sync($id);
            $this->assertNotNull($this->event($id)['deleted_at'], json_encode($change) . ' removes the event.');
        }

        $this->assertSame([], iterator_to_array((new DueDates())->all()), 'Nothing to resync.');
    }

    public function testProjectDeadlinesAreOfferedToWhoMayListProjects(): void
    {
        $this->project('Nou web', ['ends_on' => date('Y-m-d', strtotime('+5 days'))]);
        $this->project('Arxivat', ['active' => 0, 'ends_on' => date('Y-m-d', strtotime('+5 days'))]);
        $this->project('Lluny', ['ends_on' => date('Y-m-d', strtotime('+90 days'))]);
        $provider = new ProjectDeadlines();
        $from     = new DateTimeImmutable('today');
        $to       = new DateTimeImmutable('+30 days');

        $this->assertSame('tasks.projects', $provider->calendar());
        $this->assertSame([], [...$provider->events($from, $to, $this->createUser('pau@example.com', 'user'))]);

        $events = [...$provider->events($from, $to, $this->createUser('boss@example.com', 'admin'))];
        $this->assertCount(1, $events);
        $this->assertSame(lang('Tasks.cal.projectTitle', ['Nou web']), $events[0]->title);
        $this->assertTrue($events[0]->allDay);
        $this->assertStringStartsWith('project-', (string) $events[0]->key);
    }

    public function testAUserWhoLosesTheBoardLeavesTheOpenToDoTasks(): void
    {
        $pau  = $this->createUser('pau@example.com', 'user');
        $open = $this->task('Oberta', [], [(int) $pau->id]);
        $done = $this->task('Feta', ['status' => 'done'], [(int) $pau->id]);

        Events::trigger('privilegesChanged', (int) $pau->id);
        $this->assertSame(1, db_connect()->table('task_assignees')->where('task_id', $open)->countAllResults(), 'Still has the board: kept.');

        db_connect()->table('auth_groups_users')->where('user_id', $pau->id)->delete();
        (new \Modules\Core\Cache\CoreCache())->invalidate('permissions');
        Events::trigger('privilegesChanged', (int) $pau->id);

        $this->assertSame(0, db_connect()->table('task_assignees')->where('task_id', $open)->countAllResults());
        $this->assertSame(1, db_connect()->table('task_assignees')->where('task_id', $done)->countAllResults(), 'Done to-do tasks keep their history.');
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'assignee_removed')->countAllResults());
    }

    /**
     * @return array<string, mixed>
     */
    private function event(int $id): array
    {
        $row = db_connect()->table('events')->where('source_module', 'Tasks')->where('source_key', DueDates::key($id))->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }
}
