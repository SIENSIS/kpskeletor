<?php

declare(strict_types=1);

namespace Modules\Files\Services;

/**
 * The rights of the folder ACL and the RBAC permission that caps each one in the file manager
 * ("RBAC is the ceiling, the ACL decides where").
 */
final class Rights
{
    public const VIEW   = 'view';
    public const UPLOAD = 'upload';
    public const EDIT   = 'edit';
    public const DELETE = 'delete';
    public const MANAGE = 'manage';

    /** @var list<string> */
    public const ALL = [self::VIEW, self::UPLOAD, self::EDIT, self::DELETE, self::MANAGE];

    /** RBAC permission needed to use each right in the file manager. */
    public const CEILING = [
        self::VIEW   => 'files.list',
        self::UPLOAD => 'files.upload',
        self::EDIT   => 'files.edit',
        self::DELETE => 'files.delete',
        self::MANAGE => 'files.acl',
    ];

    /**
     * The known rights of a stored or posted value, in canonical order.
     *
     * @param list<mixed>|string $rights comma separated or a list
     *
     * @return list<string>
     */
    public static function parse(array|string $rights): array
    {
        $list = is_string($rights) ? explode(',', $rights) : $rights;
        $list = array_map(static fn (mixed $r): string => is_string($r) ? strtolower(trim($r)) : '', $list);

        return array_values(array_intersect(self::ALL, $list));
    }

    /**
     * @param list<string> $rights
     */
    public static function format(array $rights): string
    {
        return implode(',', self::parse($rights));
    }
}
