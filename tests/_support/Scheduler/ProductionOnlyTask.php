<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/** Every minute, production only. */
final class ProductionOnlyTask implements TaskInterface
{
    public static int $runs = 0;

    public function name(): string
    {
        return 'Production fixture';
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinute()->onlyInEnvironment('production');
    }

    public function run(): TaskResult
    {
        self::$runs++;

        return TaskResult::success();
    }
}
