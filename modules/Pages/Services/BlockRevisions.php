<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Revisions of reusable blocks (D-47-01, D-47-02): table `page_block_revisions`. A snapshot keeps the block's name, status and sections (every language); restoring
 * writes them back and records a new revision. The same screen as the pages' revisions.
 */
final class BlockRevisions
{
    /**
     * Records a revision of the block as it is now; nothing when it equals the last one.
     */
    public function record(int $blockId, ?int $userId, string $action = 'save'): ?int
    {
        $snapshot = $this->snapshot($blockId);

        if ($snapshot === null) {
            return null;
        }

        $last    = $this->latest($blockId);
        $changes = $last === null ? [] : $this->changes($last, $snapshot);

        if ($last !== null && $changes === [] && $action === 'save') {
            return null;
        }

        db_connect()->table('page_block_revisions')->insert([
            'block_id'   => $blockId,
            'user_id'    => $userId,
            'action'     => $action,
            'changes'    => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'snapshot'   => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        return (int) db_connect()->insertID();
    }

    /**
     * @return array{page: array{name: string, status: string}, sections: list<array<string, mixed>>}|null
     */
    public function snapshot(int $blockId): ?array
    {
        $block = (new PageBlocks())->find($blockId);

        if ($block === null) {
            return null;
        }

        return [
            'page'     => ['name' => (string) $block['name'], 'status' => (string) $block['status']],
            'sections' => PageSections::forBlocks()->snapshot($blockId),
        ];
    }

    /**
     * @return list<array{id: int, user: string|null, action: string, created_at: string, changes: list<string>}>
     */
    public function history(int $blockId, int $limit = 200): array
    {
        $rows = db_connect()->table('page_block_revisions')
            ->select('page_block_revisions.id, page_block_revisions.action, page_block_revisions.changes, page_block_revisions.created_at, users.username')
            ->join('users', 'users.id = page_block_revisions.user_id', 'left')
            ->where('page_block_revisions.block_id', $blockId)
            ->orderBy('page_block_revisions.id', 'desc')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(static function (array $row): array {
            $changes = json_decode((string) ($row['changes'] ?? '[]'), true);

            return [
                'id'         => (int) $row['id'],
                'user'       => $row['username'] === null ? null : (string) $row['username'],
                'action'     => (string) $row['action'],
                'created_at' => (string) $row['created_at'],
                'changes'    => is_array($changes) ? array_values(array_map('strval', $changes)) : [],
            ];
        }, $rows);
    }

    /**
     * Label => [before, after] of a revision against the one before it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function diff(int $blockId, int $revisionId): array
    {
        $revision = $this->find($blockId, $revisionId);

        if ($revision === null) {
            return [];
        }

        $previous = db_connect()->table('page_block_revisions')->where('block_id', $blockId)->where('id <', $revisionId)->orderBy('id', 'desc')->get(1)->getRowArray();
        $before   = is_array($previous) ? self::decode((string) $previous['snapshot']) : [];
        $old      = $this->flatten($before, true);
        $new      = $this->flatten($revision, true);
        $diff     = [];

        foreach ($this->changes($before, $revision) as $key) {
            $diff[$key] = [$old[$key] ?? '', $new[$key] ?? ''];
        }

        return $diff;
    }

    /**
     * @throws RuntimeException when the revision is not one of the block's
     */
    public function restore(int $blockId, int $revisionId, ?int $userId): void
    {
        $snapshot = $this->find($blockId, $revisionId);

        if ($snapshot === null || (new PageBlocks())->find($blockId) === null) {
            throw new RuntimeException(lang('Pages.errors.revisionNotFound'));
        }

        $block  = is_array($snapshot['page'] ?? null) ? $snapshot['page'] : [];
        $update = ['updated_at' => Time::now()->toDateTimeString()];

        if (is_string($block['name'] ?? null) && trim($block['name']) !== '') {
            $update['name'] = mb_substr($block['name'], 0, 120);
        }

        if (in_array($block['status'] ?? null, PageBlocks::STATUSES, true)) {
            $update['status'] = $block['status'];
        }

        $db = db_connect();
        $db->transException(true)->transStart();

        try {
            $db->table('page_blocks')->where('id', $blockId)->update($update);
            PageSections::forBlocks()->replace($blockId, is_array($snapshot['sections'] ?? null) ? array_values($snapshot['sections']) : []);
            $db->transComplete();
        } finally {
            $db->transException(false);
        }

        $this->record($blockId, $userId, 'restore');
    }

    /**
     * @return array<string, mixed>|null the snapshot of one of the block's revisions
     */
    public function find(int $blockId, int $revisionId): ?array
    {
        $row = db_connect()->table('page_block_revisions')->where('id', $revisionId)->where('block_id', $blockId)->get(1)->getRowArray();

        return is_array($row) ? self::decode((string) $row['snapshot']) : null;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return list<string>
     */
    public function changes(array $old, array $new): array
    {
        $before  = $this->flatten($old);
        $after   = $this->flatten($new);
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = $key;
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latest(int $blockId): ?array
    {
        $row = db_connect()->table('page_block_revisions')->where('block_id', $blockId)->orderBy('id', 'desc')->get(1)->getRowArray();

        return is_array($row) ? self::decode((string) $row['snapshot']) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $snapshot = json_decode($json, true);

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, string>
     */
    private function flatten(array $snapshot, bool $plain = false): array
    {
        $flat = [];

        foreach (is_array($snapshot['page'] ?? null) ? $snapshot['page'] : [] as $field => $value) {
            $flat[(string) $field] = is_scalar($value) ? (string) $value : '';
        }

        $sections = is_array($snapshot['sections'] ?? null) ? array_values($snapshot['sections']) : [];

        if ($sections !== []) {
            $flat['sections'] = $plain ? Revisions::describeSections($sections) : (string) json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $flat;
    }
}
