<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

/**
 * Summary of one run, returned by TaskRunner (the full record is in `task_runs`).
 */
final class TaskRun
{
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public readonly string $status,
        public readonly string $output,
        public readonly int $duration,
    ) {
    }
}
