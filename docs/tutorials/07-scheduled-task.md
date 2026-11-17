# Tutorial 7 — A scheduled task

A daily reminder of **overdue to-do tasks**: every morning, the assignees of each open to-do task
past its due date get a notification. You will see the scheduled-task contract, schedules,
`withoutOverlapping()`, the result and output shown in the back office, and how to test a task
that depends on the date.

Finished code:

| Piece | File |
|---|---|
| Scheduled task | `modules/Tasks/Tasks/OverdueReminder.php`, declared in `$tasks` |
| Notification | `modules/Tasks/Notifications/TaskOverdue.php` (like `TaskAssigned`, tutorial 6) |
| Query and "once a day" | `modules/Tasks/Services/TaskRepository.php` (`overdue()`, `markReminded()`) |
| Column | `tasks.reminded_on` (the day of the last reminder) |

Reference: [`scheduler.md`](../scheduler.md).

> Wording: the module's `Tasks/` folder holds its **scheduled tasks** (as in every module); the
> records of the module are **to-do tasks**.

## 1. The class

A scheduled task implements `Modules\Core\Scheduler\TaskInterface`: a name (shown in the back
office, translated), a schedule and `run()`:

```php
final class OverdueReminder implements TaskInterface
{
    public function name(): string
    {
        return lang('Tasks.scheduled.overdueReminder');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('07:30')->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $repository = new TaskRepository();
        $grace      = max(0, min(30, (int) setting('Tasks.reminderGraceDays')));
        $tasks      = $repository->overdue($grace);
        $sent       = 0;
        $reminded   = 0;

        foreach ($tasks as $task) {
            if ($task['assignees'] !== []) {
                $sent += notify($task['assignees'], new TaskOverdue([
                    'id'     => (int) $task['id'],
                    'title'  => (string) $task['title'],
                    'due_on' => (string) $task['due_on'],
                ]));
                $reminded++;
            }

            $repository->markReminded((int) $task['id']);
        }

        $output = lang('Tasks.scheduled.overdueReminderOutput', [$reminded, $sent]);

        if ($reminded > 0) {
            activity_log('Tasks', 'overdue_reminded', [
                'resource' => 'tasks-tasks',
                'summary'  => $output,
                'changes'  => ['tasks' => $reminded, 'notifications' => $sent],
            ]);
        }

        return TaskResult::success($output);
    }
}
```

- **Schedule.** `Schedule::daily('07:30')` is the cron expression `30 7 * * *` in the site's time
  zone. Other helpers: `everyMinutes(15)`, `hourlyAt(30)`, `weekly(1, '08:00')`,
  `monthly(1, '04:00')`, `cron('…')` ([`scheduler.md`, "Schedules"](../scheduler.md#schedules)).
- **`withoutOverlapping()`**: a run never starts while the previous one still holds the lock.
- **Idempotent.** `overdue()` returns only to-do tasks not reminded today
  (`reminded_on < today`), and `markReminded()` sets `reminded_on`: running the task twice the
  same day (by hand, or after a crash) sends nothing new. Design every scheduled task so a second
  run is harmless.
- **Result and output.** `TaskResult::success($output)` (or `failure()`, `skipped()`); the output
  is stored with the run and shown in Settings › Scheduled tasks. An exception is caught by the
  scheduler and recorded as a failure.
- **No email inline**: `notify()` queues the emails according to each user's preferences.
- The setting `Tasks.reminderGraceDays` is tutorial 8.

## 2. Declare it

```php
/** Scheduled tasks (tutorial 7). */
public array $tasks = [\Modules\Tasks\Tasks\OverdueReminder::class];
```

Run `php spark modules:sync`. One cron line runs every scheduled task of every enabled module:

```bash
php spark schedule:list                                   # the reminder, its next run
php spark schedule:run --task=OverdueReminder --force    # run it now, due or not
```

`--task` takes the class or its short name; `--force` runs it even when it is not due (the lock
still applies).
Without cron, the "poor man's cron" setting (`Core.poorMansCron`) runs due tasks after requests.

## 3. See it in the back office

Settings › **Scheduled tasks** (`backend/schedule`) lists the reminder with its schedule, last
run, result and output ("1 overdue to-do tasks reminded, 2 notifications sent"), and a "Run now"
button for users with `schedule.run`. The run is also in Activity (`overdue_reminded`).

## Tests

`modules/Tasks/Tests/WidgetsAndReminderTest.php`:

- `testTheReminderNotifiesAssigneesOfOverdueToDoTasksOnceADay`: runs `OverdueReminder::run()`
  directly; done, trashed and not-yet-due to-do tasks are skipped; the output; a second run the
  same day sends nothing; `Time::setTestNow()` moves to the next day and it reminds again; the
  schedule is `30 7 * * *` and prevents overlapping.
- `testTheGraceDaysSettingDelaysTheReminder` (tutorial 8).

```php
Time::setTestNow(Time::now()->addDays(1));
$task->run();
Time::setTestNow();
$this->assertSame(3, $this->notificationsOf((int) $pau->id, TaskOverdue::class));
```

Always reset `Time::setTestNow()` in the same test.

```bash
vendor/bin/phpunit modules/Tasks/Tests/WidgetsAndReminderTest.php --filter Reminder
```

Next: [tutorial 8](08-setting.md), a setting for this reminder.
