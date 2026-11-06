<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/** Daily at 03:00, without overlapping (10-minute lock). */
final class LockedTask implements TaskInterface
{
    public static int $runs = 0;

    public function name(): string
    {
        return 'Locked fixture';
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('03:00')->withoutOverlapping(10);
    }

    public function run(): TaskResult
    {
        self::$runs++;

        return TaskResult::success();
    }
}
