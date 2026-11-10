<?php

declare(strict_types=1);

namespace Modules\News\Tasks;

use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;
use Modules\News\Services\Publisher;

/**
 * Every 5 minutes: publishes the scheduled posts whose date has come (notifying the audience of
 * private notices) and marks notices past their expiry date as `expired` (unpinned). Readers do
 * not wait for it: the visibility rules check the dates themselves.
 */
final class ExpireNotices implements TaskInterface
{
    public function name(): string
    {
        return lang('News.tasks.expireNotices');
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinutes(5)->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $publisher = new Publisher();
        $released  = $publisher->releaseScheduled();
        $expired   = $publisher->expireNotices();

        if ($released > 0 || $expired > 0) {
            activity_log('News', 'notices_updated', [
                'resource' => 'news-posts',
                'summary'  => lang('News.tasks.expireNoticesOutput', [$released, $expired]),
                'changes'  => ['published' => $released, 'expired' => $expired],
            ]);
        }

        return TaskResult::success(lang('News.tasks.expireNoticesOutput', [$released, $expired]));
    }
}
