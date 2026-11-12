<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;
use Modules\Files\Policies\PolicyRegistry;

/**
 * The folder ACL (docs/files.md, "Folder permissions").
 *
 * `rights()` is the ACL alone (what serving a private file checks): superadmins get every right
 * everywhere except in module folders; the owner of a personal space gets every right in it;
 * `files.all` gets every right everywhere except in module folders and in other users' personal
 * spaces (D-22-04: only superadmins enter those, and their reads are logged); otherwise the nearest folder of the chain (the folder, then its parents) that
 * defines its own permissions decides, and its entries for the user, the user's groups and
 * "all logged-in users" add up. No such folder: no rights (default deny). Module folders are
 * written only by their module's code: `files.all` may view the ones whose policy is `readonly`.
 *
 * `managerRights()` is what the file manager allows: the ACL capped by the RBAC permission of
 * each right (`Rights::CEILING`), personal spaces only for the groups of
 * `Files.personalSpaceGroups` and shared folders of the private disk only with `files.private`.
 */
final class FolderAcl
{
    /** @var array<string, list<string>> */
    private array $memo = [];

    public function __construct(
        private readonly FolderRepository $folders = new FolderRepository(),
        private readonly ?PolicyRegistry $policies = null,
    ) {
    }

    public function folders(): FolderRepository
    {
        return $this->folders;
    }

    /**
     * @return list<string>
     */
    public function rights(?User $user, string $disk, string $path): array
    {
        if ($user === null || $user->id === null || $path === '') {
            return [];
        }

        $key = $user->id . '|' . $disk . '|' . $path;

        return $this->memo[$key] ??= $this->compute($user, $disk, $path);
    }

    public function can(?User $user, string $disk, string $path, string $right): bool
    {
        return in_array($right, $this->rights($user, $disk, $path), true);
    }

    /**
     * @return list<string>
     */
    public function managerRights(User $user, string $disk, string $path): array
    {
        $auth = Authorization::for($user);

        if (! $auth->can('files.list')) {
            return [];
        }

        $space = Spaces::of($disk, $path);
        $all   = $auth->can('files.all');
        $super = Authorization::isSuperadmin($user);

        if ($space === Spaces::PERSONAL && ! $super && ! Spaces::personalEnabled($user)) {
            return [];
        }

        if ($space === Spaces::SHARED && $disk === 'private' && ! $all && ! $auth->can('files.private')) {
            return [];
        }

        $rights = [];

        foreach ($this->rights($user, $disk, $path) as $right) {
            if ($auth->can(Rights::CEILING[$right])) {
                $rights[] = $right;
            }
        }

        // The ACL of a personal space is only edited by superadmins (its owner cannot share it).
        if ($space === Spaces::PERSONAL && ! $super) {
            $rights = array_values(array_diff($rights, [Rights::MANAGE]));
        }

        return $rights;
    }

    /**
     * Whether reading a file of this folder is a superadmin entering someone else's personal
     * space (logged as a `security` entry, D-22-04).
     */
    public static function isForeignPersonal(?User $user, string $disk, string $path): bool
    {
        $owner = Spaces::personalOwner($disk, $path);

        return $owner !== null && $user !== null && $owner !== (int) $user->id && Authorization::isSuperadmin($user);
    }

    /**
     * Writes the `security` entry of a superadmin reading a file of someone else's personal space.
     */
    public static function logForeignRead(?User $user, string $disk, string $path, string $name, string $how): void
    {
        if (! self::isForeignPersonal($user, $disk, $path)) {
            return;
        }

        security_log('Files', 'personal_file_read', [
            'resource' => 'files',
            'summary'  => $disk . ':' . $path . '/' . $name,
            'after'    => ['owner' => Spaces::personalOwner($disk, $path), 'how' => $how],
        ]);
    }

    public function managerCan(User $user, string $disk, string $path, string $right): bool
    {
        return in_array($right, $this->managerRights($user, $disk, $path), true);
    }

    /**
     * Whether a registered folder of the chain defines permissions (the folder ACL governs the
     * path); false for files outside every managed folder.
     */
    public function governs(string $disk, string $path): bool
    {
        if (Spaces::personalOwner($disk, $path) !== null) {
            return true;
        }

        foreach (Spaces::chain($path) as $folder) {
            if (($this->folders->find($disk, $folder)['own_permissions'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The folder whose permissions apply (the nearest with its own), or null.
     *
     * @return array{id: int, disk: string, path: string, owner_id: int|null, quota: int|null, own_permissions: bool}|null
     */
    public function source(string $disk, string $path): ?array
    {
        foreach (Spaces::chain($path) as $folder) {
            $found = $this->folders->find($disk, $folder);

            if ($found !== null && $found['own_permissions']) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function compute(User $user, string $disk, string $path): array
    {
        $auth = Authorization::for($user);

        if (Spaces::of($disk, $path) === Spaces::MODULES) {
            $policy = ($this->policies ?? PolicyRegistry::instance())->for($path);

            return $policy !== null && $policy->browse() === 'readonly' && $auth->can('files.all') ? [Rights::VIEW] : [];
        }

        if (Authorization::isSuperadmin($user)) {
            return Rights::ALL;
        }

        $owner = Spaces::personalOwner($disk, $path);

        if ($owner === (int) $user->id) {
            return Rights::ALL;
        }

        // Other users' personal spaces: never through `files.all` (D-22-04), only their ACL.
        if ($owner === null && $auth->can('files.all')) {
            return Rights::ALL;
        }

        $source = $this->source($disk, $path);

        if ($source === null) {
            return [];
        }

        $groups = $user->getGroups() ?? [];
        $rights = [];

        foreach ($this->folders->permissions($source['id']) as $entry) {
            $applies = match ($entry['subject_type']) {
                'authenticated' => true,
                'group'         => in_array($entry['subject_id'], $groups, true),
                'user'          => $entry['subject_id'] === (string) $user->id,
                default         => false,
            };

            if ($applies) {
                $rights = [...$rights, ...$entry['rights']];
            }
        }

        return Rights::parse($rights);
    }
}
