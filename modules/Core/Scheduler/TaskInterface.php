<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

/**
 * A scheduled task: a class in a module's `Tasks/` folder declared in the manifest (`$tasks`) and
 * run by `php spark schedule:run` (docs/scheduler.md).
 *
 * ```php
 * final class PruneDrafts implements TaskInterface
 * {
 *     public function name(): string { return lang('News.tasks.pruneDrafts'); }
 *     public function schedule(): Schedule { return Schedule::daily('03:00')->withoutOverlapping(); }
 *     public function run(): TaskResult { …; return TaskResult::success('3 drafts deleted.'); }
 * }
 * ```
 *
 * The class is built without arguments. A thrown exception counts as a failure: it is logged,
 * administrators are notified and the other tasks still run.
 */
interface TaskInterface
{
    /** Name shown in the back office and `schedule:list` (a language string). */
    public function name(): string;

    public function schedule(): Schedule;

    public function run(): TaskResult;
}
