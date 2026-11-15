<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Content\PublishWorkflow;
use Modules\News\Notifications\NewNotice;

/**
 * Publishing rules of posts (docs/news.md, "Statuses"):
 *
 * - **publish**: `published_at` defaults to now; a future date makes the post `scheduled`,
 *   otherwise `published`;
 * - a `private` or `groups` **notice** that becomes readable notifies its audience once
 *   (`notified_at`): `private` → every user with `news-read.view`, `groups` → the members of its
 *   groups;
 * - the ExpireNotices task releases scheduled posts whose date has come (and notifies) and marks
 *   notices past `expires_at` as `expired` (unpinned).
 *
 * Readers never depend on the task: `NewsVisibility` checks the dates itself.
 */
final class Publisher
{
    /**
     * Publishes a post now or at its `published_at`; returns the new status.
     *
     * @param array<string, mixed> $row the `news` row
     */
    public function publish(array $row): string
    {
        $now = Time::now();
        ['status' => $status, 'at' => $at] = PublishWorkflow::statusFor(is_string($row['published_at'] ?? null) ? $row['published_at'] : null, $now);

        db_connect()->table('news')->where('id', (int) $row['id'])->update([
            'status'       => $status,
            'published_at' => $at->toDateTimeString(),
            'updated_at'   => $now->toDateTimeString(),
        ]);

        if ($status === 'published') {
            $this->notifyAudience((int) $row['id']);
        }

        NoticeCalendar::sync((int) $row['id']);

        return $status;
    }

    /**
     * Sends the "new notice" notification of a private/groups notice once, when it is readable.
     * Returns the number of users reached.
     */
    public function notifyAudience(int $id): int
    {
        $row = db_connect()->table('news')->where('id', $id)->get()->getRowArray();

        if (! is_array($row) || $row['type'] !== 'notice' || $row['visibility'] === 'public' || $row['notified_at'] !== null
            || $row['deleted_at'] !== null || ! in_array($row['status'], ['published', 'scheduled'], true)) {
            return 0;
        }

        $now = Time::now();

        if ($row['published_at'] === null || Time::parse((string) $row['published_at'])->isAfter($now)
            || ($row['expires_at'] !== null && ! Time::parse((string) $row['expires_at'])->isAfter($now))) {
            return 0;
        }

        // Mark first: a failure while sending must not send it twice on the next run.
        db_connect()->table('news')->where('id', $id)->where('notified_at', null)->update(['notified_at' => $now->toDateTimeString()]);

        if (db_connect()->affectedRows() !== 1) {
            return 0;
        }

        $notice = new NewNotice([
            'id'       => $id,
            'title'    => $this->title($id),
            'priority' => (string) $row['priority'],
        ]);

        if ($row['visibility'] === 'private') {
            return notifyPermission('news-read.view', $notice);
        }

        $groups = db_connect()->table('news_groups')->select('groups.name')
            ->join('groups', 'groups.id = news_groups.group_id')
            ->where('news_groups.news_id', $id)
            ->get()->getResultArray();
        $names = array_map(static fn (array $group): string => (string) $group['name'], $groups);

        return $names === [] ? 0 : notifyGroup($names, $notice);
    }

    /**
     * Scheduled posts whose date has come become `published` (and notify). Returns how many.
     */
    public function releaseScheduled(): int
    {
        $ids = PublishWorkflow::releaseScheduled('news', 'published_at');

        foreach ($ids as $id) {
            $this->notifyAudience($id);
            NoticeCalendar::sync($id);
        }

        return count($ids);
    }

    /**
     * Notices past `expires_at` become `expired` and are unpinned. Returns how many.
     */
    public function expireNotices(): int
    {
        $now = Time::now()->toDateTimeString();
        $ids = array_column(db_connect()->table('news')->select('id')
            ->where('type', 'notice')
            ->whereIn('status', ['published', 'scheduled'])
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <=', $now)
            ->get()->getResultArray(), 'id');

        if ($ids === []) {
            return 0;
        }

        db_connect()->table('news')->whereIn('id', $ids)->update(['status' => 'expired', 'pinned' => 0, 'updated_at' => $now]);
        $count = db_connect()->affectedRows();
        NoticeCalendar::syncMany($ids);

        return $count;
    }

    /**
     * The title of a post in Catalan (stored in the notification; the reader's language is used
     * for the rest of the text).
     */
    private function title(int $id): string
    {
        $rows  = db_connect()->table('news_translations')->select('locale, title')->where('news_id', $id)->get()->getResultArray();
        $texts = array_column($rows, 'title', 'locale');

        return (string) ($texts[NewsRepository::FALLBACK_LOCALE] ?? reset($texts) ?: '');
    }
}
