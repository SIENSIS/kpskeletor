<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Storage\Storage;
use Modules\Files\Policies\PolicyRegistry;

/**
 * What the file manager shows a user: the folder tree of each space and the items of a folder,
 * as JSON-ready arrays with the user's rights (`FolderAcl::managerRights()`). Folders the user
 * cannot view are only listed when a subfolder below them is visible (to reach it).
 */
final class Browser
{
    public function __construct(
        private readonly FolderAcl $acl = new FolderAcl(),
        private readonly FileRepository $files = new FileRepository(),
        private readonly QuotaService $quotas = new QuotaService(),
    ) {
    }

    public function acl(): FolderAcl
    {
        return $this->acl;
    }

    /**
     * Creates the folders the spaces need: the user's personal space (when it is shown to them)
     * and the shared roots of both disks (no entries: only `files.all` until an administrator
     * shares them).
     */
    public function ensureSpaces(User $user): void
    {
        $folders = $this->acl->folders();

        foreach (Spaces::DISKS as $disk) {
            $folders->ensure($disk, 'shared', ['own_permissions' => true]);
        }

        if (Spaces::personalEnabled($user)) {
            $folders->ensure('private', Spaces::personalPath((int) $user->id), ['owner_id' => (int) $user->id, 'own_permissions' => true]);
        }
    }

    /**
     * The roots of the tree: list of spaces with their folders.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(User $user): array
    {
        $roots = [];
        $auth  = Authorization::for($user);

        if (Spaces::personalEnabled($user)) {
            $path = Spaces::personalPath((int) $user->id);

            if (($node = $this->branch($user, 'private', $path, lang('Files.spaces.personal'), 'bi bi-person')) !== null) {
                $roots[] = $node;
            }
        }

        foreach (Spaces::DISKS as $disk) {
            if (($node = $this->branch($user, $disk, 'shared', lang('Files.spaces.shared_' . $disk), $disk === 'public' ? 'bi bi-globe' : 'bi bi-lock')) !== null) {
                $roots[] = $node;
            }
        }

        // Other users' personal spaces: superadmins only (D-22-04).
        if (Authorization::isSuperadmin($user)) {
            $others = [];

            foreach ($this->acl->folders()->below('private', 'users') as $path => $folder) {
                if (preg_match('#^users/([1-9][0-9]*)$#', $path, $m) === 1 && (int) $m[1] !== (int) $user->id) {
                    $node = $this->branch($user, 'private', $path, $this->userName((int) $m[1]), 'bi bi-person');

                    if ($node !== null) {
                        $others[] = $node;
                    }
                }
            }

            if ($others !== []) {
                $roots[] = ['disk' => 'private', 'path' => 'users', 'name' => lang('Files.spaces.users'), 'icon' => 'bi bi-people', 'rights' => [], 'children' => $others];
            }
        }

        if ($auth->can('files.all')) {
            $modules = [];

            foreach (PolicyRegistry::instance()->all() as $folder => $policy) {
                if ($policy->browse() === 'readonly') {
                    foreach (Spaces::DISKS as $disk) {
                        $modules[] = ['disk' => $disk, 'path' => $folder, 'name' => $folder . ' (' . lang('Files.disks.' . $disk) . ')', 'icon' => 'bi bi-puzzle', 'rights' => [Rights::VIEW], 'children' => [], 'readonly' => true];
                    }
                }
            }

            if ($modules !== []) {
                $roots[] = ['disk' => 'public', 'path' => 'modules', 'name' => lang('Files.spaces.modules'), 'icon' => 'bi bi-puzzle', 'rights' => [], 'children' => $modules];
            }
        }

        return $roots;
    }

    /**
     * The contents of a folder for a user who may view it.
     *
     * @param array{q?: string, type?: string, from?: string, to?: string, tag?: string, sort?: string} $filters
     *
     * @return array<string, mixed>
     */
    public function contents(User $user, string $disk, string $path, array $filters = []): array
    {
        $rights = $this->acl->managerRights($user, $disk, $path);
        $items  = [];

        if (Spaces::of($disk, $path) === Spaces::MODULES) {
            foreach ($this->files->storedIn($disk, $path) as $row) {
                $items[] = $this->storedItem($row);
            }
        } else {
            foreach ($this->files->inFolder($disk, $path, $filters) as $row) {
                $items[] = $this->item($row, $rights);
            }
        }

        $folders = [];

        foreach ($this->acl->folders()->below($disk, $path) as $child => $folder) {
            if (Spaces::parent($child) === $path && $this->visible($user, $disk, $child)) {
                $folders[] = ['disk' => $disk, 'path' => $child, 'name' => Spaces::basename($child), 'rights' => $this->acl->managerRights($user, $disk, $child)];
            }
        }

        $crumbs = [];

        foreach (array_reverse(Spaces::chain($path)) as $folder) {
            $crumbs[] = ['path' => $folder, 'name' => $this->folderName($disk, $folder)];
        }

        return [
            'disk'        => $disk,
            'path'        => $path,
            'name'        => $this->folderName($disk, $path),
            'rights'      => $rights,
            'space'       => Spaces::of($disk, $path),
            'root'        => Spaces::isRoot($disk, $path),
            'folders'     => $folders,
            'files'       => $items,
            'breadcrumbs' => $crumbs,
            'quota'       => $this->quotas->summary($disk, $path),
        ];
    }

    /**
     * A file as the file manager shows it.
     *
     * @param array<string, mixed> $row  FileRepository row
     * @param list<string>         $rights the user's rights in its folder
     *
     * @return array<string, mixed>
     */
    public function item(array $row, array $rights): array
    {
        $id       = (int) $row['id'];
        $base     = site_url('backend/files/api/file/' . $id);
        $mime     = (string) $row['mime'];
        $image    = in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp'], true);
        $previewable = $image || $mime === 'application/pdf' || in_array((string) $row['extension'], ['txt', 'csv', 'md'], true);
        $public   = (string) $row['disk'] === 'public';

        return [
            'id'          => $id,
            'name'        => (string) $row['name'],
            'extension'   => (string) $row['extension'],
            'mime'        => $mime,
            'size'        => (int) $row['size'],
            'width'       => isset($row['width']) ? (int) $row['width'] : null,
            'height'      => isset($row['height']) ? (int) $row['height'] : null,
            'alt'         => (string) $row['alt'],
            'tags'        => (string) $row['tags'] === '' ? [] : explode(',', (string) $row['tags']),
            'visibility'  => (string) $row['visibility'],
            'created_at'  => (string) $row['created_at'],
            'deleted_at'  => $row['deleted_at'] ?? null,
            'folder'      => (string) $row['folder'],
            'disk'        => (string) $row['disk'],
            'image'       => $image,
            'thumbnail'   => $image && ($row['thumbnail'] ?? null) !== null ? $base . '/thumbnail' : null,
            'preview'     => $previewable ? $base . '/preview' : null,
            'download'    => $base . '/download',
            'publicUrl'   => $public ? $this->storage()->disk('public')->url((string) $row['path']) : null,
            'privateUrl'  => $public ? null : site_url('files/private/' . $row['file_key']),
            'rights'      => $rights,
        ];
    }

    /**
     * Whether a folder appears in the tree: viewable, or the way to a viewable subfolder.
     */
    public function visible(User $user, string $disk, string $path): bool
    {
        foreach (array_keys($this->acl->folders()->below($disk, $path)) as $folder) {
            if ($this->acl->managerCan($user, $disk, $folder, Rights::VIEW)) {
                return true;
            }
        }

        return false;
    }

    public function folderName(string $disk, string $path): string
    {
        if ($path === 'shared') {
            return lang('Files.spaces.shared_' . $disk);
        }

        if (preg_match('#^users/([1-9][0-9]*)$#', $path, $m) === 1 && $disk === 'private') {
            return (int) $m[1] === (int) auth()->id() ? lang('Files.spaces.personal') : $this->userName((int) $m[1]);
        }

        return Spaces::basename($path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function branch(User $user, string $disk, string $path, string $name, string $icon): ?array
    {
        if (! $this->visible($user, $disk, $path)) {
            return null;
        }

        $all   = $this->acl->folders()->below($disk, $path);
        $nodes = [];

        foreach ($all as $folder => $row) {
            if ($this->visible($user, $disk, $folder)) {
                $nodes[$folder] = ['disk' => $disk, 'path' => $folder, 'name' => $folder === $path ? $name : Spaces::basename($folder), 'icon' => $folder === $path ? $icon : 'bi bi-folder', 'rights' => $this->acl->managerRights($user, $disk, $folder), 'children' => []];
            }
        }

        // Attach children to parents, deepest first (paths are sorted).
        foreach (array_reverse(array_keys($nodes)) as $folder) {
            if ($folder !== $path && isset($nodes[Spaces::parent($folder)])) {
                array_unshift($nodes[Spaces::parent($folder)]['children'], $nodes[$folder]);
            }
        }

        return $nodes[$path] ?? null;
    }

    /**
     * @param array<string, mixed> $row stored_files row
     *
     * @return array<string, mixed>
     */
    private function storedItem(array $row): array
    {
        $public = (string) $row['disk'] === 'public';
        $mime   = (string) $row['mime'];

        return [
            'id' => null, 'name' => (string) $row['original_name'], 'extension' => (string) $row['extension'], 'mime' => $mime,
            'size' => (int) $row['size'], 'width' => null, 'height' => null, 'alt' => '', 'tags' => [], 'visibility' => $public ? 'public' : 'private',
            'created_at' => (string) $row['created_at'], 'deleted_at' => null, 'folder' => Spaces::parent((string) $row['path']), 'disk' => (string) $row['disk'],
            'image' => false, 'thumbnail' => null, 'preview' => null,
            'download' => $public ? $this->storage()->disk('public')->url((string) $row['path']) : site_url('files/private/' . $row['file_key'] . '?download=1'),
            'publicUrl' => $public ? $this->storage()->disk('public')->url((string) $row['path']) : null,
            'privateUrl' => $public ? null : site_url('files/private/' . $row['file_key']), 'rights' => [Rights::VIEW], 'readonly' => true,
        ];
    }

    private function userName(int $id): string
    {
        $row = db_connect()->table('users')->select('username')->where('id', $id)->get()->getRowArray();

        return is_array($row) && (string) $row['username'] !== '' ? (string) $row['username'] : '#' . $id;
    }

    private function storage(): Storage
    {
        return service('storage');
    }
}
