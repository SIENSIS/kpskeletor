<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Scheduled task of the {{Name}} module (example, docs/scheduler.md): run by
 * `php spark schedule:run` when due. Change the schedule and the work, or remove it from the
 * manifest's `$tasks`.
 */
final class {{Name}}Task implements TaskInterface
{
    public function name(): string
    {
        return lang('{{Name}}.task.description');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('03:00')->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        return TaskResult::success();
    }
}
