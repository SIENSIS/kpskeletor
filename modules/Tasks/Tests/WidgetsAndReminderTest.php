<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use CodeIgniter\I18n\Time;
use Modules\Tasks\Notifications\TaskOverdue;
use Modules\Tasks\Tasks\OverdueReminder;

/**
 * The widgets (tutorial 4), the overdue reminder (tutorial 7) and its setting (tutorial 8).
 *
 * @internal
 */
final class WidgetsAndReminderTest extends TasksTestCase
{
    public function testMyPendingToDoTasksShowsOnlyTheUsersOpenToDoTasks(): void
    {
        $pau   = $this->createUser('pau@example.com', 'user');
        $other = $this->createUser('laia@example.com', 'user');
        $this->task('Pendent meva', ['due_on' => date('Y-m-d', strtotime('-1 day'))], [(int) $pau->id]);
        $this->task('Feta meva', ['status' => 'done'], [(int) $pau->id]);
        $this->task('D’una altra', [], [(int) $other->id]);
        $this->login('pau@example.com');

        $response = $this->send('GET', 'backend');
        $this->assertContains('tasks.pending', $this->cards($response));
        $this->assertNotContains('tasks.activity', $this->cards($response), 'The chart needs tasks-tasks.list.');

        $home = $this->body('GET', 'backend');
        $this->assertStringContainsString('Pendent meva', $home);
        $this->assertStringNotContainsString('Feta meva', $home);
        $this->assertStringNotContainsString('D’una altra', $home);
    }

    public function testTheActivityChartIsAsynchronousAndNeedsItsPermission(): void
    {
        $this->task('Creada avui');
        $this->task('Feta avui', ['status' => 'done', 'completed_at' => Time::now()->toDateTimeString()]);

        $this->createUser('pau@example.com', 'user');
        $this->login('pau@example.com');
        $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->assertStatus(403);

        $this->send('POST', 'logout');
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->assertContains('tasks.activity', $this->cards($this->send('GET', 'backend')));

        $json = json_decode((string) $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->response()->getBody(), true);
        $this->assertSame(2, $json['data']['created']);
        $this->assertSame(1, $json['data']['completed']);
        $this->assertCount(14, $json['data']['days']);
        $this->assertStringContainsString('<svg', $json['html']);
        $this->assertStringNotContainsString('style=', $json['html']);

        // Hidden for the group: neither the card nor its data.
        $this->setGroupWidget('admin', 'tasks.activity', false);
        $this->assertNotContains('tasks.activity', $this->cards($this->send('GET', 'backend')));
        $this->send('GET', 'backend/dashboard/widgets/tasks.activity/data')->assertStatus(403);
    }

    public function testTheReminderNotifiesAssigneesOfOverdueToDoTasksOnceADay(): void
    {
        $pau  = $this->createUser('pau@example.com', 'user');
        $laia = $this->createUser('laia@example.com', 'user');
        $late = $this->task('Vençuda', ['due_on' => date('Y-m-d', strtotime('-2 days'))], [(int) $pau->id, (int) $laia->id]);
        $this->task('Avui', ['due_on' => date('Y-m-d')], [(int) $pau->id]);
        $this->task('Feta', ['due_on' => date('Y-m-d', strtotime('-5 days')), 'status' => 'done'], [(int) $pau->id]);
        $this->task('Paperera', ['due_on' => date('Y-m-d', strtotime('-5 days')), 'deleted_at' => Time::now()->toDateTimeString()], [(int) $pau->id]);

        $task   = new OverdueReminder();
        $result = $task->run();

        $this->assertFalse($result->failed());
        $this->assertStringContainsString(lang('Tasks.scheduled.overdueReminderOutput', [1, 2]), $result->storedOutput());
        $this->assertSame(1, $this->notificationsOf((int) $pau->id, TaskOverdue::class));
        $this->assertSame(1, $this->notificationsOf((int) $laia->id, TaskOverdue::class));
        $this->assertSame(date('Y-m-d'), $this->taskRow($late)['reminded_on']);

        // A second run the same day sends nothing new.
        $task->run();
        $this->assertSame(1, $this->notificationsOf((int) $pau->id, TaskOverdue::class));

        // The next day it reminds again, and the to-do task due today is overdue too.
        Time::setTestNow(Time::now()->addDays(1));
        $task->run();
        Time::setTestNow();
        $this->assertSame(3, $this->notificationsOf((int) $pau->id, TaskOverdue::class));
        $this->assertSame(2, $this->notificationsOf((int) $laia->id, TaskOverdue::class));

        $this->assertSame('30 7 * * *', $task->schedule()->expression());
        $this->assertTrue($task->schedule()->preventsOverlapping(), 'Two runs never overlap.');
    }

    public function testTheGraceDaysSettingDelaysTheReminder(): void
    {
        $pau = $this->createUser('pau@example.com', 'user');
        $this->task('Vençuda ahir', ['due_on' => date('Y-m-d', strtotime('-2 days'))], [(int) $pau->id]);

        setting()->set('Tasks.reminderGraceDays', 3);
        (new OverdueReminder())->run();
        $this->assertSame(0, $this->notificationsOf((int) $pau->id, TaskOverdue::class));

        setting()->set('Tasks.reminderGraceDays', 1);
        (new OverdueReminder())->run();
        $this->assertSame(1, $this->notificationsOf((int) $pau->id, TaskOverdue::class));
        setting()->forget('Tasks.reminderGraceDays');
    }

    public function testTheSettingsAreValidatedInTheSettingsScreen(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $page = $this->body('GET', 'backend/settings/tasks');
        $this->assertStringContainsString(lang('Tasks.settings.reminderGraceDays'), $page);
        $this->assertStringContainsString(lang('Tasks.settings.publicRequests'), $page);
    }
}
