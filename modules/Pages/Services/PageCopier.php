<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * The "duplicate" operation: a copy of a page as a new **draft** under the same parent, with its
 * texts (titles marked as a copy, slugs made unique among the siblings), groups and template;
 * the copy belongs to the user who made it and starts its own revision history. Children and
 * revisions are not copied.
 */
final class PageCopier
{
    public function duplicate(int $pageId, ?int $userId): int
    {
        $db  = db_connect();
        $row = $db->table('pages')->where('id', $pageId)->where('deleted_at', null)->get(1)->getRowArray();

        if (! is_array($row)) {
            throw new RuntimeException(lang('Pages.errors.notFound'));
        }

        $now    = Time::now()->toDateTimeString();
        $parent = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $slugs  = new PageSlugs();

        $db->transException(true)->transStart();

        try {
            $db->table('pages')->insert([
                'parent_id'      => $parent,
                'position'       => (int) $row['position'] + 1,
                'template'       => $row['template'],
                'status'         => 'draft',
                'publish_at'     => null,
                'visibility'     => $row['visibility'],
                'noindex'        => $row['noindex'],
                'featured_image' => $row['featured_image'],
                'author_id'      => $userId,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $copy = (int) $db->insertID();

            foreach ($db->table('page_translations')->where('page_id', $pageId)->get()->getResultArray() as $text) {
                unset($text['id']);
                $text['page_id'] = $copy;
                $text['title']   = mb_substr(lang('Pages.copyOf', [(string) $text['title']]), 0, 190);
                $base            = substr((string) $text['slug'], 0, 170) . '-' . lang('Pages.copySlug');
                $text['slug']    = $base;

                for ($i = 2; $slugs->taken($text['slug'], (string) $text['locale'], $parent, $copy); $i++) {
                    $text['slug'] = $base . '-' . $i;
                }

                $db->table('page_translations')->insert($text);
            }

            foreach ($db->table('page_groups')->select('group_id')->where('page_id', $pageId)->get()->getResultArray() as $group) {
                $db->table('page_groups')->insert(['page_id' => $copy, 'group_id' => (int) $group['group_id']]);
            }

            $db->transComplete();
        } finally {
            $db->transException(false);
        }

        (new Revisions())->record($copy, $userId, 'duplicate');

        return $copy;
    }
}
