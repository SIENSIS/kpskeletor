<?php

declare(strict_types=1);

namespace Modules\Core\Content;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * The audience of module content (news, pages…): `public` (everyone, the public site), `private`
 * (any signed-in user) or `groups` (members of the groups linked in a junction table such as
 * `news_groups` or `page_groups`). Extracted from News in session 20 (docs/specification.md,
 * "News" › visibility, "Pages").
 *
 * No raw SQL: every value is bound or escaped by the Query Builder.
 */
final class Audience
{
    public const VISIBILITIES = ['public', 'private', 'groups'];

    /**
     * Adds the "visible to this user" conditions to a query on a content table (or an alias).
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     * @param string $linkTable  junction table of the `groups` audience (e.g. `page_groups`)
     * @param string $linkKey    its column pointing to the content row (e.g. `page_id`)
     * @param bool   $publicOnly only `public` rows, even for a signed-in user
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    public static function apply(BaseBuilder $builder, ?User $user, string $alias, string $linkTable, string $linkKey, bool $publicOnly = false): BaseBuilder
    {
        if ($publicOnly || $user === null) {
            return $builder->where($alias . '.visibility', 'public');
        }

        $groups = self::groupIds($user);

        $builder->groupStart()
            ->whereIn($alias . '.visibility', ['public', 'private']);

        if ($groups !== []) {
            $builder->orGroupStart()
                ->where($alias . '.visibility', 'groups')
                ->whereIn($alias . '.id', static fn (BaseBuilder $sub): BaseBuilder => $sub->select($linkKey)->from($linkTable)->whereIn('group_id', $groups))
                ->groupEnd();
        }

        return $builder->groupEnd();
    }

    /**
     * Ids of the user's groups (`groups.id` of the Shield group names).
     *
     * @return list<int>
     */
    public static function groupIds(User $user): array
    {
        $names = $user->getGroups() ?? [];

        if ($names === []) {
            return [];
        }

        $rows = db_connect()->table('groups')->select('id')->whereIn('name', $names)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
