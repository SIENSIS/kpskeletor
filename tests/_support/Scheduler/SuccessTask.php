<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/** Every minute; prints a line and succeeds. Counts its runs. */
final class SuccessTask implements TaskInterface
{
    public static int $runs = 0;

    public function name(): string
    {
        return 'Success fixture';
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinute();
    }

    public function run(): TaskResult
    {
        self::$runs++;
        echo 'printed by the task';

        return TaskResult::success('All good.');
    }
}
