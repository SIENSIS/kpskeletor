<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;

/**
 * The predefined spaces of the file manager (docs/files.md):
 *
 * - personal: `users/{user_id}` on the private disk; the owner has every right, nobody else
 *   (except superadmins, whose reads are logged; D-22-04) unless the folder ACL of a subfolder
 *   says so; shown in the file
 *   manager to members of the groups in `Files.personalSpaceGroups`;
 * - modules: `modules/<module>` on either disk; only the module's code writes there; hidden in
 *   the file manager, or read-only for `files.all` when the module's `FileAccessPolicy` says so;
 * - shared: `shared/…` on either disk, created by administrators, with the rights of its ACL.
 */
final class Spaces
{
    public const PERSONAL = 'personal';
    public const MODULES  = 'modules';
    public const SHARED   = 'shared';

    /** @var list<string> */
    public const DISKS = ['public', 'private'];

    public static function personalPath(int $userId): string
    {
        return 'users/' . $userId;
    }

    /**
     * The space of a folder (null: the disk root or a path outside every space).
     */
    public static function of(string $disk, string $path): ?string
    {
        if ($disk === 'private' && preg_match('#^users/[1-9][0-9]*(/|$)#', $path) === 1) {
            return self::PERSONAL;
        }

        if ($path === 'modules' || str_starts_with($path, 'modules/')) {
            return self::MODULES;
        }

        if ($path === 'shared' || str_starts_with($path, 'shared/')) {
            return self::SHARED;
        }

        return null;
    }

    /**
     * The owner of a personal folder (or of a folder inside one).
     */
    public static function personalOwner(string $disk, string $path): ?int
    {
        if ($disk !== 'private' || preg_match('#^users/([1-9][0-9]*)(/|$)#', $path, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * Whether a folder is the root of a space (it cannot be renamed or deleted).
     */
    public static function isRoot(string $disk, string $path): bool
    {
        return $path === 'shared' || $path === 'modules' || $path === 'users'
            || ($disk === 'private' && preg_match('#^users/[1-9][0-9]*$#', $path) === 1)
            || preg_match('#^modules/[^/]+$#', $path) === 1;
    }

    /**
     * Whether the user sees their personal space in the file manager (`Files.personalSpaceGroups`).
     */
    public static function personalEnabled(User $user): bool
    {
        if (Authorization::isSuperadmin($user)) {
            return true;
        }

        $groups = setting('Files.personalSpaceGroups');
        $groups = is_array($groups) ? array_map('strval', $groups) : [];

        return array_intersect($groups, $user->getGroups() ?? []) !== [];
    }

    /**
     * The folder and its parents, nearest first: `a/b/c` → `a/b/c`, `a/b`, `a`.
     *
     * @return list<string>
     */
    public static function chain(string $path): array
    {
        $chain = [];

        while ($path !== '' && $path !== '.') {
            $chain[] = $path;
            $slash   = strrpos($path, '/');
            $path    = $slash === false ? '' : substr($path, 0, $slash);
        }

        return $chain;
    }

    public static function parent(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? '' : substr($path, 0, $slash);
    }

    public static function basename(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }
}
