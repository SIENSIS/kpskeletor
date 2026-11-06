<?php

declare(strict_types=1);

namespace Modules\Core\Tasks;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Modules\Core\Scheduler\Schedule;
use Modules\Core\Scheduler\TaskInterface;
use Modules\Core\Scheduler\TaskResult;

/**
 * Deletes the activity log entries older than `Core.activityRetentionDays` (default 365), once a
 * day, in chunks so a large table does not lock for long. The only way entries are removed
 * (the log is append-only); each purge is itself written to the log.
 */
final class PurgeActivityLog implements TaskInterface
{
    /** Rows deleted per statement. */
    public const CHUNK = 1000;

    /** Statements per run (the rest is deleted the next day). */
    public const MAX_CHUNKS = 500;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    public function name(): string
    {
        return lang('Core.schedule.tasks.purgeActivityLog');
    }

    public function schedule(): Schedule
    {
        return Schedule::daily('03:17')->withoutOverlapping(120);
    }

    public function run(): TaskResult
    {
        $days    = max(1, (int) setting('Core.activityRetentionDays'));
        $deleted = $this->purge(Time::now()->subDays($days)->format('Y-m-d H:i:s'));

        if ($deleted > 0) {
            activity_log('Core', 'purge', ['resource' => 'activity-log', 'summary' => (string) $deleted, 'changes' => ['days' => $days, 'deleted' => $deleted]]);
        }

        return TaskResult::success(lang('Core.schedule.purgeResult', [$deleted, $days]));
    }

    /**
     * Deletes the entries created before `$before` (`Y-m-d H:i:s`); returns how many.
     */
    public function purge(string $before): int
    {
        $db      = $this->db ?? db_connect();
        $deleted = 0;

        for ($i = 0; $i < self::MAX_CHUNKS; $i++) {
            $ids = array_column($db->table('activity_log')->select('id')
                ->where('created_at <', $before)->orderBy('id')->limit(self::CHUNK)
                ->get()->getResultArray(), 'id');

            if ($ids === []) {
                break;
            }

            $db->table('activity_log')->whereIn('id', $ids)->delete();
            $deleted += count($ids);

            if (count($ids) < self::CHUNK) {
                break;
            }
        }

        return $deleted;
    }
}
