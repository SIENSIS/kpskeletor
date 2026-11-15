<?php

declare(strict_types=1);

namespace Modules\Users\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\Users\Services\BirthdayGreetings;

/**
 * Every day at `Users.birthdayGreetingTime` (08:00 by default; changing the setting moves the task
 * on the scheduler's next sync): greets the members of the chosen groups on their birthday
 * (`Services/BirthdayGreetings.php`, D-25-09).
 */
final class SendBirthdayGreetings implements TaskInterface
{
    public function name(): string
    {
        return lang('Users.greetings.task');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily(BirthdayGreetings::time())->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        return TaskResult::success(lang('Users.greetings.taskOutput', [(new BirthdayGreetings())->sendToday()]));
    }
}
