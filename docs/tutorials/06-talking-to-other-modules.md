# Tutorial 6 — Talking to other modules

A module never calls into another module's internals and never hard-codes itself in the Core.
It talks to the rest of the application through the Core's contracts:

1. a **notification type**, sent to the assignees of a to-do task;
2. the **calendar**: due dates **published** (push) and project deadlines **provided** on demand
   (pull);
3. **events**: listening to an event another module (here, the Core's grant manager) triggers.

Finished code:

| Piece | File |
|---|---|
| Notification type | `modules/Tasks/Notifications/TaskAssigned.php`, declared in `$notifications` |
| Who to notify | `modules/Tasks/Services/Assignees.php` (`notifyNew()`) |
| Calendar (push) | `modules/Tasks/Calendar/DueDates.php` (`CalendarSource`), `$calendars['due']` |
| Calendar (pull) | `modules/Tasks/Calendar/ProjectDeadlines.php` (`CalendarProvider`), `$calendars['projects']`, `$calendarProviders` |
| Event listeners | `modules/Tasks/Config/Events.php` |

References: [`notifications.md`](../notifications.md), [`calendar.md`](../calendar.md).

## 1. A notification type

A notification type is a class in `Notifications/` that extends
`Modules\Core\Notifications\NotificationType`: its name and description (shown in each user's
notification preferences), an icon, and `render()`, which turns the stored data into the message
in the reader's language:

```php
final class TaskAssigned extends NotificationType
{
    public static function title(): string
    {
        return lang('Tasks.notifications.assigned');
    }

    public static function description(): string
    {
        return lang('Tasks.notifications.assignedHelp');
    }

    public static function icon(): string
    {
        return 'bi bi-person-check';
    }

    public function render(): NotificationMessage
    {
        $id  = (int) ($this->data['id'] ?? 0);
        $due = (string) ($this->data['due_on'] ?? '');

        return new NotificationMessage(
            lang('Tasks.notifications.assignedTitle', [(string) ($this->data['title'] ?? '')]),
            $due !== '' ? lang('Tasks.notifications.assignedDue', [$due]) : lang('Tasks.notifications.assignedBody'),
            url: $id > 0 ? 'backend/tasks/tasks/show/' . Crud::maskId($id, 'tasks') : 'backend/tasks',
        );
    }
}
```

Declare it so the preferences screen lists it:

```php
public array $notifications = [
    \Modules\Tasks\Notifications\TaskAssigned::class,
    // …
];
```

Send it with `notify()` (users), `notifyGroup()` or `notifyPermission()`; the Core stores it,
shows it in the bell and, if the user's preferences say so, queues the email. Never send email
inline. `Assignees::notifyNew()` notifies only the users **added** by a change, never the user
who made it:

```php
$added = array_values(array_diff($repository->assigneeIds($taskId), $before, $actor === null ? [] : [$actor]));

if ($added === []) {
    return 0;
}

return notify($added, new TaskAssigned([
    'id'     => $taskId,
    'title'  => (string) $task['title'],
    'due_on' => (string) ($task['due_on'] ?? ''),
]));
```

The CRUD calls it from its `afterWrite()` hook (tutorial 2) and the API from its own (tutorial 11).
Store only what `render()` needs (ids and short texts), never secrets.

## 2. The calendar

The Events module shows one calendar with the dates of every module. A module never writes to its
`events` table: it declares its calendars and either **publishes** events when its data changes
(push) or **answers** when the calendar asks for a range (pull). With the Events module disabled,
`service('calendar')` is a null publisher and nothing breaks.

```php
public array $calendars = [
    'due'      => ['label' => 'Tasks.cal.due', 'color' => '#fd7e14', 'source' => \Modules\Tasks\Calendar\DueDates::class],
    'projects' => ['label' => 'Tasks.cal.projects', 'color' => '#6610f2', 'permission' => 'tasks-projects.list', 'hidden' => true],
];

public array $calendarProviders = [\Modules\Tasks\Calendar\ProjectDeadlines::class];
```

Calendar ids are `<module>.<key>`: `tasks.due` and `tasks.projects`.

### Push: due dates

`DueDates::sync($id)` publishes the due date of an open to-do task as an all-day event seen only
by its assignees and its creator, and removes it when the to-do task is done, trashed or has no
date:

```php
public static function sync(int $id): bool
{
    $row     = db_connect()->table('tasks')->where('id', $id)->get(1)->getRowArray();
    $event   = is_array($row) ? self::event($row) : null;
    $service = self::calendar();

    return $event === null ? $service->unpublish(self::MODULE, self::key($id)) : $service->publish(self::MODULE, self::key($id), $event);
}
```

`event()` builds a `Modules\Core\Calendar\CalendarEvent` with the title in the three languages,
`visibility: 'restricted'` and the user ids, a link to the to-do task, and `reminders: true`.
Call `sync()` after **every** change: the CRUD's `afterWrite()`, `TaskRepository::setStatus()`
(board, `complete`, bulk action) and the API. Because `DueDates` implements `CalendarSource`
(`all()` yields every event that should exist), `php spark calendar:resync Tasks` repairs any
drift.

### Pull: project deadlines

Data that changes elsewhere, or that depends on who asks, can be provided instead of stored. A
`CalendarProvider` is asked for a range and a user, and returns events computed on the fly:

```php
public function events(DateTimeInterface $from, DateTimeInterface $to, User $user): iterable
{
    if (! Authorization::for($user)->can('tasks-projects.list')) {
        return [];
    }
    // … active projects whose ends_on is in the range, as all-day CalendarEvents
}
```

The provider checks the permission itself, on top of the calendar's `permission`: authorise on
the server, every time.

## 3. Listening to another module's event

`Config/Events.php` of a module is discovered by CodeIgniter. The Core's `GrantManager` triggers
`privilegesChanged` (user id) when a user's groups or permissions change; the Tasks module removes
a user who can no longer open the board from the open to-do tasks:

```php
Events::on('privilegesChanged', static function (mixed $userId): void {
    if (is_numeric($userId) && (new ModuleState())->isEnabled('Tasks')) {
        Assignees::dropIfNoAccess((int) $userId);
    }
});
```

Two rules for listeners:

- **Event files load even while the module is disabled**: check `ModuleState::isEnabled()`.
- Check permissions through `Authorization::for($user)` (as `dropIfNoAccess()` does), and log what
  you change: `activity_log('Tasks', 'assignee_removed', …)` with its label in
  `Tasks.activity.assignee_removed` (ca, es, en).

The same file also registers the attachments' access policy with the Files module's
`filesAccessPolicies` event (tutorial 10).

## Try it

As `admin`, assign a to-do task with a due date to the demo `editor`: the editor's bell shows
"To-do task assigned to you", and their calendar (`backend/calendar`) shows the due date under
"To-do task due dates". Mark it done: the event disappears. Turn on "Project deadlines" in the
calendar's list to see the projects' deadlines. Remove the editor from every group in Users: they
leave the open to-do tasks (Activity shows `assignee_removed`).

## Tests

`modules/Tasks/Tests/CalendarAndEventsTest.php`:

- `testDueDatesArePublishedToTheAssigneesAndTheCreator` (restricted audience, idempotent `sync()`)
- `testDoneTrashedOrUndatedToDoTasksLeaveTheCalendar`
- `testProjectDeadlinesAreOfferedToWhoMayListProjects`
- `testAUserWhoLosesTheBoardLeavesTheOpenToDoTasks` (triggers `privilegesChanged`)

`modules/Tasks/Tests/TasksModuleTest.php`:
`testAddingAToDoTaskSetsTheCreatorNotifiesAssigneesAndPublishesTheDueDate` (the notification
reaches the new assignee only, once).

```bash
vendor/bin/phpunit modules/Tasks/Tests/CalendarAndEventsTest.php
```

Next: [tutorial 7](07-scheduled-task.md), a daily reminder.
