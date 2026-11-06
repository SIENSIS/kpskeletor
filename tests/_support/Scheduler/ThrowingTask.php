<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/** Every minute; throws. */
final class ThrowingTask implements TaskInterface
{
    public function name(): string
    {
        return 'Throwing fixture';
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinute();
    }

    public function run(): TaskResult
    {
        throw new \RuntimeException('Disk on fire');
    }
}
