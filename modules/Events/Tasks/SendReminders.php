<?php

declare(strict_types=1);

namespace Modules\Events\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\Events\Services\Reminders;

/**
 * Every 5 minutes: sends the event reminders users asked for (`Services/Reminders.php`), once per
 * occurrence, only for events the user can still see.
 */
final class SendReminders implements TaskInterface
{
    public function name(): string
    {
        return lang('Events.tasks.sendReminders');
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinutes(5)->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $sent = (new Reminders())->sendDue();

        return TaskResult::success(lang('Events.tasks.sendRemindersOutput', [$sent]));
    }
}
