<?php

declare(strict_types=1);

namespace Modules\Core\Tasks;

use Modules\Core\Mail\MailQueue;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Sends the queued notification emails every minute (a batch per run) and deletes the old ones.
 */
final class SendQueuedEmails implements TaskInterface
{
    /** Emails sent per run. */
    public const BATCH = 50;

    public function __construct(private readonly MailQueue $queue = new MailQueue())
    {
    }

    public function name(): string
    {
        return lang('Core.schedule.tasks.sendQueuedEmails');
    }

    public function schedule(): Schedule
    {
        return Schedule::everyMinute()->withoutOverlapping(15);
    }

    public function run(): TaskResult
    {
        $result = $this->queue->sendPending(self::BATCH);
        $pruned = $this->queue->prune();
        $output = lang('Core.schedule.mailResult', [$result['sent'], $result['retry'], $result['failed'], $this->queue->pendingCount(), $pruned]);

        // Retries are expected (a mail server down for a while); a final failure is not.
        return $result['failed'] > 0 ? TaskResult::failure($output) : TaskResult::success($output);
    }
}
