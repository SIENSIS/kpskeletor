# Scheduled tasks

One cron entry runs every scheduled task of every enabled module (specification "Scheduled
tasks"; decisions D-11-05, D-11-06). Texts and docs always say **scheduled task**, never just
"task" (the tutorial module `Tasks` has "to-do tasks", D-00-56).

## The cron line

Linux hosts (the web server's user, every minute):

```bash
* * * * * cd /path/to/project && php spark schedule:run >> /dev/null 2>&1
```

Shared hosting: add the same command in the control panel's "Cron jobs" page, with the full path
to PHP if needed (e.g. `/usr/local/bin/php /home/acme/site/spark schedule:run`). The command
needs the same `.env` as the site.

Without cron, see [The fallback without cron](#the-fallback-without-cron).

## Writing a scheduled task

A class in the module's `Tasks/` folder that implements `Modules\Core\Scheduler\TaskInterface`,
declared in the manifest:

```php
// modules/News/Tasks/PurgeDrafts.php
namespace Modules\News\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

final class PurgeDrafts implements TaskInterface
{
    public function name(): string
    {
        return lang('News.tasks.purgeDrafts');   // ca, es and en
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('03:00')->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $deleted = model(PostModel::class)->purgeOldDrafts();

        return TaskResult::success(lang('News.tasks.purged', [$deleted]));
    }
}
```

```php
// modules/News/Config/Module.php
public array $tasks = [\Modules\News\Tasks\PurgeDrafts::class];
```

Then `php spark modules:sync` (it stores the declaration) and `php spark schedule:list`.
`php spark make:module` already writes an example (`Tasks/<Name>Task.php`).

- The class is built without arguments; it runs without a logged-in user. Check permissions with
  `Authorization::for($user)` and send notifications with `notifyPermission()` / `notify()`.
- `run()` returns `TaskResult::success($output)`, `TaskResult::failure($output)` or
  `TaskResult::skipped($output)`. The output (and anything the task prints) is stored with the
  run (60 KB at most) and shown in the back office: never put secrets in it.
- A thrown exception is a failure. Failures are logged, notified to the users holding
  `schedule.list` (notification "A scheduled task failed") and never stop the other tasks.
- External calls only through `OutboundClient` (docs/outbound-requests.md).

## Schedules

| Helper | Cron expression |
|---|---|
| `Schedule::everyMinute()` | `* * * * *` |
| `Schedule::everyMinutes(15)` | `*/15 * * * *` |
| `Schedule::hourly()` / `hourlyAt(30)` | `0 * * * *` / `30 * * * *` |
| `Schedule::daily('03:00')` | `0 3 * * *` |
| `Schedule::weekly(1, '08:00')` (0 or 7 = Sunday) | `0 8 * * 1` |
| `Schedule::monthly(1, '04:00')` | `0 4 1 * *` |
| `Schedule::cron('*/10 8-18 * * 1-5')` | any five-field expression |

Expressions accept `*`, lists, ranges, steps, month and weekday names (`jan`, `mon`) and the
macros `@hourly`, `@daily`, `@weekly`, `@monthly`, `@yearly`. When both the day of the month and
the day of the week are restricted, either one matches (as in cron). Times are in the
application's time zone (`Config\App::$appTimezone`).

Options:

- `->withoutOverlapping(60)`: a run never starts while the previous one holds the lock; the lock
  is in the database (works on every host) and expires after the given minutes if the process
  dies. A run that finds it taken is recorded as **skipped**.
- `->onlyInEnvironment('production')`: other environments do not run it.

## How runs are decided

Each task has a row in `scheduled_tasks` with its **next run**. `schedule:run` runs the tasks
whose next run has passed and moves it forward in one atomic update first, so:

- two `schedule:run` processes (or cron plus the fallback) never run the same slot twice;
- a run missed while cron was down happens **once** at the next call, not once per missed slot;
- a new task waits for its first slot; a changed schedule is recalculated.

Tasks of disabled modules (and classes that no longer load) are marked inactive and do not run.
Every run goes to `task_runs` (the latest 50 per task): start, duration, result, output and who
started it (schedule, "run now", the fallback without cron).

## Commands and screen

| | |
|---|---|
| `php spark schedule:run` | Runs the due scheduled tasks (exit code 1 if one failed) |
| `php spark schedule:run --task=<name>` | Only that one, if it is due (class or short class name) |
| `php spark schedule:run --task=<name> --force` | Runs it now, due or not (the lock still applies) |
| `php spark schedule:list` | Every scheduled task: schedule, next run, last result, notes |

Back office › Settings › **Scheduled tasks** (`backend/schedule`, kpcrudy): every task with its
schedule, next and last run, result and duration (`schedule.list`); the detail view lists the
latest runs with their output (`schedule.show`); **Run now** (`schedule.run`, POST with CSRF,
checked on the server) runs it immediately. Administrators hold `schedule.*`.

## The fallback without cron

For hosts without cron, turn on the setting `Core.poorMansCron` ("Run scheduled tasks without
cron"; in the Settings screen from session 14, until then `setting('Core.poorMansCron', true)`). Then
back-office requests run the due tasks **at most once a minute**, after the response:

- with PHP-FPM or LiteSpeed the page is sent first (`fastcgi_finish_request()`), so the user does
  not wait;
- with mod_php or `php spark serve` the browser waits until the tasks finish;
- it needs back-office visits: nothing runs while nobody uses the back office.

A real cron entry is always more reliable.

## Notification emails

The Core's own scheduled task **Send the queued notification emails** (`SendQueuedEmails`, every
minute, without overlapping) sends the email channel of the notifications
(docs/notifications.md): 50 emails per run, retried up to 5 times with growing waits; sent and
failed emails are deleted after 30 days. Without cron (or the fallback) notification emails are
never sent.

## Troubleshooting

- **Nothing runs:** `php spark schedule:list` shows the next runs. Run `php spark schedule:run`
  by hand; check the cron line uses the right PHP and path, and the log in `writable/logs/`.
- **A task is missing:** its module is disabled, it is not in the manifest's `$tasks`, or
  `php spark modules:sync` has not been run. A class that fails to load is logged and inactive.
- **Always "skipped":** a previous run still holds the lock (or died: it expires after the
  `withoutOverlapping()` minutes).
- **Tables missing:** run `php spark modules:sync`.
