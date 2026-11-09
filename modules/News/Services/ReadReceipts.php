<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\I18n\Time;

/**
 * Read receipts (`news_reads`): "mark as read" from the Notices widget and the reader screen,
 * counted for authors with `news-posts.readers`. A user can only mark posts they may read (the
 * callers check it with `NewsVisibility`).
 */
final class ReadReceipts
{
    /**
     * Marks a post as read by a user; true when it was not read before.
     */
    public function markRead(int $newsId, int $userId): bool
    {
        if ($this->isRead($newsId, $userId)) {
            return false;
        }

        return db_connect()->table('news_reads')->insert([
            'news_id' => $newsId,
            'user_id' => $userId,
            'read_at' => Time::now()->toDateTimeString(),
        ]);
    }

    public function isRead(int $newsId, int $userId): bool
    {
        return db_connect()->table('news_reads')->where('news_id', $newsId)->where('user_id', $userId)->countAllResults() > 0;
    }

    /**
     * Which of these posts the user has read.
     *
     * @param list<int> $newsIds
     *
     * @return array<int, true>
     */
    public function readAmong(array $newsIds, int $userId): array
    {
        if ($newsIds === []) {
            return [];
        }

        $rows = db_connect()->table('news_reads')->select('news_id')->where('user_id', $userId)->whereIn('news_id', $newsIds)->get()->getResultArray();

        return array_fill_keys(array_map(static fn (array $row): int => (int) $row['news_id'], $rows), true);
    }

    public function count(int $newsId): int
    {
        return db_connect()->table('news_reads')->where('news_id', $newsId)->countAllResults();
    }

    /**
     * The readers of a post, newest first.
     *
     * @return list<array{username: string, read_at: string}>
     */
    public function readers(int $newsId, int $limit = 500): array
    {
        $rows = db_connect()->table('news_reads')
            ->select('users.username, news_reads.read_at')
            ->join('users', 'users.id = news_reads.user_id')
            ->where('news_reads.news_id', $newsId)
            ->orderBy('news_reads.read_at', 'desc')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(static fn (array $row): array => ['username' => (string) $row['username'], 'read_at' => (string) $row['read_at']], $rows);
    }
}
