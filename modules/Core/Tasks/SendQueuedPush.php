<?php

declare(strict_types=1);

namespace Modules\Core\Tasks;

use Modules\Core\Push\PushQueue;
use Modules\Core\Push\WebPush;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Sends the queued push notifications every minute (a batch per run, through OutboundClient)
 * and deletes the old ones. While the channel is off nothing is sent (the queue waits).
 */
final class SendQueuedPush implements TaskInterface
{
    public function __construct(private readonly PushQueue $queue = new PushQueue())
    {
    }

    public function name(): string
    {
        return lang('Push.task.name');
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinute()->withoutOverlapping(15);
    }

    public function run(): TaskResult
    {
        $pruned = $this->queue->prune();

        if (! WebPush::enabled()) {
            return TaskResult::success(lang('Push.task.off', [$this->queue->pendingCount(), $pruned]));
        }

        $result = $this->queue->sendPending();
        $output = lang('Push.task.result', [$result['sent'], $result['retry'], $result['failed'], $result['removed'], $this->queue->pendingCount(), $pruned]);

        // Retries and devices that are gone are expected; a final failure is not.
        return $result['failed'] > 0 ? TaskResult::failure($output) : TaskResult::success($output);
    }
}
