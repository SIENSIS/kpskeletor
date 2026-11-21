<?php

declare(strict_types=1);

namespace Modules\Contact\Tasks;

use Modules\Contact\Services\ContactMessages;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Retention of the contact messages (D-48-02): every day at 03:40, deletes the messages older
 * than `Contact.retentionDays` (365 by default, never fewer than 30).
 */
final class PurgeMessages implements TaskInterface
{
    public function name(): string
    {
        return lang('Contact.scheduled.purge');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('03:40')->withoutOverlapping();
    }

    public function run(): TaskResult
    {
        $days    = max(30, (int) setting('Contact.retentionDays'));
        $deleted = (new ContactMessages())->purge($days);
        $output  = lang('Contact.scheduled.purgeOutput', [$deleted, $days]);

        if ($deleted > 0) {
            activity_log('Contact', 'messages_purged', [
                'resource' => 'contact-messages',
                'summary'  => $output,
                'changes'  => ['deleted' => $deleted, 'days' => $days],
            ]);
        }

        return TaskResult::success($output);
    }
}
