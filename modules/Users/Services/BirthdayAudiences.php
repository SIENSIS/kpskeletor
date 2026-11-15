<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * The default rows of `group_birthday_audiences` (D-25-08): each reserved group sees its own
 * members and `admin` sees every reserved group. Written when the table is created (sites that
 * already had the groups) and, on a fresh install, when `modules:sync` creates the reserved groups
 * after the migrations (`GroupRegistry::EVENT_RESERVED_CREATED`, `Config/Events.php`); never when
 * the table already has rows.
 */
final class BirthdayAudiences
{
    public const RESERVED = ['superadmin', 'admin', 'editor', 'user'];

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     *
     * @return int rows written
     */
    public static function seedDefaults(?BaseConnection $db = null): int
    {
        $db ??= db_connect();

        if (! $db->tableExists('groups') || $db->table('group_birthday_audiences')->countAllResults() > 0) {
            return 0;
        }

        $ids    = array_column($db->table('groups')->select('id, name')->whereIn('name', self::RESERVED)->get()->getResultArray(), 'id', 'name');
        $insert = [];

        foreach ($ids as $name => $id) {
            $insert[] = ['group_id' => (int) $id, 'target_group_id' => (int) $id];

            if ($name === 'admin') {
                foreach ($ids as $other => $target) {
                    if ($other !== 'admin') {
                        $insert[] = ['group_id' => (int) $id, 'target_group_id' => (int) $target];
                    }
                }
            }
        }

        if ($insert !== []) {
            $db->table('group_birthday_audiences')->insertBatch($insert);
        }

        return count($insert);
    }
}
