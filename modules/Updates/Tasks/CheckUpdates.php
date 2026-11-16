<?php

declare(strict_types=1);

namespace Modules\Updates\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\Updates\Services\ReleaseChecker;

/**
 * Daily update check (ReleaseChecker): stores the result and notifies new versions. A network
 * error is reported as a failed run (and shown on the page and the widget), never thrown.
 */
final class CheckUpdates implements TaskInterface
{
    public function __construct(private readonly ?ReleaseChecker $checker = null)
    {
    }

    public function name(): string
    {
        return lang('Updates.task.check');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('05:17');
    }

    public function run(): TaskResult
    {
        $status = ($this->checker ?? service('updatechecker'))->check();
        $newer  = ReleaseChecker::newer($status);

        if (is_string($status['error'] ?? null)) {
            return TaskResult::failure($status['error']);
        }

        if ($newer === []) {
            return TaskResult::success(lang('Updates.msg.upToDate'));
        }

        return TaskResult::success(lang('Updates.msg.available', [implode(', ', array_map(static fn (string $name, string $version): string => $name . ' ' . $version, array_keys($newer), $newer))]));
    }
}
