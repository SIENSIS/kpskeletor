<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Security\HtmlSanitizer;
use RuntimeException;

/**
 * Revisions of pages (docs/pages.md, "Revisions"): every save keeps a snapshot of the page, its
 * texts in every language and its groups, with who saved it, when and which fields changed
 * compared with the previous revision. Restoring a revision writes its content back (texts,
 * groups, template, visibility, noindex, featured image) and records a new revision; the tree
 * position (parent, order) and the publication (status, date) are not restored: they have their
 * own fields and permission (`pages.publish`).
 */
final class Revisions
{
    /** Columns of `pages` kept in a snapshot. */
    public const PAGE_FIELDS = ['parent_id', 'position', 'template', 'status', 'publish_at', 'visibility', 'noindex', 'featured_image'];

    /** Columns written back by a restore. */
    public const RESTORED_FIELDS = ['template', 'visibility', 'noindex', 'featured_image'];

    /**
     * Records a revision of the page as it is now; nothing when it equals the last revision.
     * Returns the new revision id, or null.
     */
    public function record(int $pageId, ?int $userId, string $action = 'save'): ?int
    {
        $snapshot = $this->snapshot($pageId);

        if ($snapshot === null) {
            return null;
        }

        $last    = $this->latest($pageId);
        $changes = $last === null ? [] : $this->changes($last['snapshot'], $snapshot);

        if ($last !== null && $changes === [] && $action === 'save') {
            return null;
        }

        db_connect()->table('page_revisions')->insert([
            'page_id'    => $pageId,
            'user_id'    => $userId,
            'action'     => $action,
            'changes'    => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'snapshot'   => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        return (int) db_connect()->insertID();
    }

    /**
     * The page as it is now, or null when it does not exist.
     *
     * @return array{page: array<string, mixed>, translations: array<string, array<string, string>>, groups: list<int>}|null
     */
    public function snapshot(int $pageId): ?array
    {
        $row = db_connect()->table('pages')->where('id', $pageId)->get(1)->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $page = [];

        foreach (self::PAGE_FIELDS as $field) {
            $page[$field] = $row[$field] === null ? null : (string) $row[$field];
        }

        // Forms edit dates to the minute: seconds are not a change.
        if ($page['publish_at'] !== null) {
            $page['publish_at'] = substr($page['publish_at'], 0, 16);
        }

        $translations = [];
        $rows         = db_connect()->table('page_translations')->where('page_id', $pageId)->orderBy('locale')->get()->getResultArray();

        foreach ($rows as $text) {
            foreach (PageRepository::TEXTS as $field) {
                $translations[(string) $text['locale']][$field] = (string) ($text[$field] ?? '');
            }
        }

        $groups = array_map('intval', array_column(db_connect()->table('page_groups')->select('group_id')
            ->where('page_id', $pageId)->orderBy('group_id')->get()->getResultArray(), 'group_id'));

        return ['page' => $page, 'translations' => $translations, 'groups' => $groups];
    }

    /**
     * The revisions of a page, newest first, with the author's name and the changed fields.
     *
     * @return list<array{id: int, user: string|null, action: string, created_at: string, changes: list<string>}>
     */
    public function history(int $pageId, int $limit = 200): array
    {
        $rows = db_connect()->table('page_revisions')
            ->select('page_revisions.id, page_revisions.action, page_revisions.changes, page_revisions.created_at, users.username')
            ->join('users', 'users.id = page_revisions.user_id', 'left')
            ->where('page_revisions.page_id', $pageId)
            ->orderBy('page_revisions.id', 'desc')
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
     * One revision of a page with its snapshot, or null (another page's revision included).
     *
     * @return array{id: int, page_id: int, snapshot: array<string, mixed>, created_at: string}|null
     */
    public function find(int $pageId, int $revisionId): ?array
    {
        $row = db_connect()->table('page_revisions')->where('id', $revisionId)->where('page_id', $pageId)->get(1)->getRowArray();

        return is_array($row) ? $this->decode($row) : null;
    }

    /**
     * The field-by-field differences between a revision and the one before it: label => [before, after]
     * (plain text; rich text without tags).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function diff(int $pageId, int $revisionId): array
    {
        $revision = $this->find($pageId, $revisionId);

        if ($revision === null) {
            return [];
        }

        $previous = db_connect()->table('page_revisions')->where('page_id', $pageId)->where('id <', $revisionId)
            ->orderBy('id', 'desc')->get(1)->getRowArray();
        $before = is_array($previous) ? $this->decode($previous)['snapshot'] : ['page' => [], 'translations' => [], 'groups' => []];
        $diff   = [];

        $old = $this->flatten($before, true);
        $new = $this->flatten($revision['snapshot'], true);

        foreach ($this->changes($before, $revision['snapshot']) as $key) {
            $diff[$key] = [$old[$key] ?? '', $new[$key] ?? ''];
        }

        return $diff;
    }

    /**
     * Writes a revision's content back to the page and records a new revision.
     *
     * @throws RuntimeException when the revision does not belong to the page
     */
    public function restore(int $pageId, int $revisionId, ?int $userId): void
    {
        $revision = $this->find($pageId, $revisionId);

        if ($revision === null) {
            throw new RuntimeException(lang('Pages.errors.revisionNotFound'));
        }

        $snapshot = $revision['snapshot'];
        $page     = is_array($snapshot['page'] ?? null) ? $snapshot['page'] : [];
        $current  = db_connect()->table('pages')->where('id', $pageId)->get(1)->getRowArray();

        if (! is_array($current)) {
            throw new RuntimeException(lang('Pages.errors.revisionNotFound'));
        }

        $parent = $current['parent_id'] === null ? null : (int) $current['parent_id'];
        $slugs  = new PageSlugs();
        $db     = db_connect();

        $db->transException(true)->transStart();

        try {
            $update = ['updated_at' => Time::now()->toDateTimeString()];

            foreach (self::RESTORED_FIELDS as $field) {
                if (array_key_exists($field, $page)) {
                    $update[$field] = $page[$field];
                }
            }

            if (isset($update['featured_image']) && is_string($update['featured_image']) && service('storage')->find($update['featured_image']) === null) {
                unset($update['featured_image']); // the file is gone: keep the current image
            }

            $db->table('pages')->where('id', $pageId)->update($update);
            $db->table('page_translations')->where('page_id', $pageId)->delete();

            foreach (is_array($snapshot['translations'] ?? null) ? $snapshot['translations'] : [] as $locale => $fields) {
                if (! array_key_exists((string) $locale, ContentTranslations::LOCALES) || ! is_array($fields)) {
                    continue;
                }

                $row = ['page_id' => $pageId, 'locale' => (string) $locale];

                foreach (PageRepository::TEXTS as $field) {
                    $row[$field] = (string) ($fields[$field] ?? '');
                }

                // The body is cleaned again (the sanitiser may be stricter now); a slug taken
                // meanwhile by a sibling gets a suffix.
                $row['body'] = HtmlSanitizer::clean($row['body']);
                $base        = $row['slug'] !== '' ? $row['slug'] : 'page';
                $row['slug'] = $base;

                for ($i = 2; $slugs->taken($row['slug'], (string) $locale, $parent, $pageId); $i++) {
                    $row['slug'] = $base . '-' . $i;
                }

                $db->table('page_translations')->insert($row);
            }

            $db->table('page_groups')->where('page_id', $pageId)->delete();
            $existing = array_map('intval', array_column($db->table('groups')->select('id')->get()->getResultArray(), 'id'));

            foreach (is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [] as $group) {
                if (in_array((int) $group, $existing, true)) {
                    $db->table('page_groups')->insert(['page_id' => $pageId, 'group_id' => (int) $group]);
                }
            }

            $db->transComplete();
        } finally {
            $db->transException(false);
        }

        $this->record($pageId, $userId, 'restore');
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return list<string> changed keys (`title.ca`, `visibility`, `groups`…)
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
     * The snapshot of the latest revision of a page, or null.
     *
     * @return array{snapshot: array<string, mixed>}|null
     */
    private function latest(int $pageId): ?array
    {
        $row = db_connect()->table('page_revisions')->where('page_id', $pageId)->orderBy('id', 'desc')->get(1)->getRowArray();

        return is_array($row) ? ['snapshot' => $this->decode($row)['snapshot']] : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, page_id: int, snapshot: array<string, mixed>, created_at: string}
     */
    private function decode(array $row): array
    {
        $snapshot = json_decode((string) $row['snapshot'], true);

        return [
            'id'         => (int) $row['id'],
            'page_id'    => (int) $row['page_id'],
            'snapshot'   => is_array($snapshot) ? $snapshot : [],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * A snapshot as flat key => text: `visibility`, `title.ca`, `body.es`, `groups`; with $plain,
     * rich text without its tags (for people to read).
     *
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, string>
     */
    private function flatten(array $snapshot, bool $plain = false): array
    {
        $flat = [];

        foreach (is_array($snapshot['page'] ?? null) ? $snapshot['page'] : [] as $field => $value) {
            $flat[(string) $field] = (string) ($value ?? '');
        }

        foreach (is_array($snapshot['translations'] ?? null) ? $snapshot['translations'] : [] as $locale => $fields) {
            foreach (is_array($fields) ? $fields : [] as $field => $value) {
                $text = (string) $value;

                if ($plain && $field === 'body') {
                    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>', '</h4>'], "\n", $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                }

                if ($text !== '') {
                    $flat[$field . '.' . $locale] = $text;
                }
            }
        }

        $groups        = is_array($snapshot['groups'] ?? null) ? array_map('intval', $snapshot['groups']) : [];
        $flat['groups'] = implode(',', $groups);

        return $flat;
    }
}
