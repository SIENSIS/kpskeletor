<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Registry\ModuleState;
use Modules\Core\Storage\Picker\StorageBackend;
use Modules\Core\Storage\Picker\UploadToken;
use Modules\Core\Storage\StoredFile;
use RuntimeException;
use Throwable;

/**
 * The file picker's backend while the Files module is enabled (`Config\Storage::$pickerBackend`
 * through Config/Registrar.php; docs/files.md, "File picker"). Otherwise the Core's.
 *
 * - **Upload:** the token grants it (no `files.*` permission and no ACL check: the field decided
 *   the folder). The target folder and its parents are created on demand with the space's
 *   default rights (`users/{id}` belongs to its owner; `users/{id}/avatar` has its own ACL:
 *   `view` for every signed-in user); the quotas of the chain apply; the file gets a `files` row
 *   (alt text, tags, thumbnail) and an activity entry.
 * - **Browse / choose:** inside the token's roots only; the field's own folder is always allowed
 *   (the module chose it), any other folder needs the ACL's `view` right (not the file manager's
 *   RBAC ceiling: the picker is not the file manager).
 */
final class PickerBackend extends StorageBackend
{
    /** The avatar subfolder of a personal space: readable by every signed-in user. */
    public const AVATAR_FOLDER = '#^users/[1-9][0-9]*/avatar$#';

    public function __construct(
        private readonly FolderAcl $acl = new FolderAcl(),
        private readonly QuotaService $quotas = new QuotaService(),
        private readonly FileRepository $files = new FileRepository(),
        private readonly ThumbnailService $thumbnails = new ThumbnailService(),
    ) {
    }

    public function upload(User $user, UploadToken $token, UploadedFile $file): StoredFile
    {
        if (! self::enabled()) {
            return parent::upload($user, $token, $file);
        }

        $this->prepare($token->disk, $token->folder, (int) $user->id);
        $this->quotas->check($token->disk, $token->folder, max(0, (int) $file->getSize()));

        $storage = $this->storage();
        $stored  = $storage->store($file, $token->disk, $token->folder, self::rules($token));

        try {
            $this->quotas->check($token->disk, $token->folder, $stored->size - max(0, (int) $file->getSize()));
        } catch (RuntimeException $e) {
            $storage->delete($stored);

            throw $e;
        }

        $id = $this->files->create($stored, $token->folder, $this->thumbnails->make($stored));
        activity_log('Files', 'file_uploaded', [
            'resource'  => 'files',
            'record_id' => (string) $id,
            'summary'   => $token->disk . ':' . $token->folder . '/' . $stored->originalName,
            'after'     => ['via' => 'picker'],
        ]);

        return $stored;
    }

    public function browse(User $user, UploadToken $token, string $path): ?array
    {
        if (! self::enabled()) {
            return parent::browse($user, $token, $path);
        }

        if (! $this->readable($user, $token, $path)) {
            return null;
        }

        $folders = [];

        foreach ($this->acl->folders()->below($token->disk, $path) as $child => $folder) {
            if (Spaces::parent($child) === $path && $this->readable($user, $token, $child)) {
                $folders[] = ['path' => $child, 'name' => Spaces::basename($child)];
            }
        }

        $files   = [];
        $storage = $this->storage();

        foreach ($this->files->inFolder($token->disk, $path, ['sort' => 'date']) as $row) {
            $stored = $storage->findById((int) $row['stored_file_id']);

            if ($stored !== null) {
                $files[] = $stored;
            }
        }

        // Files the module stored before the picker existed (no `files` row) in its own folder.
        if ($token->inFolder($path)) {
            foreach ($this->files->storedIn($token->disk, $path) as $row) {
                if ($this->files->findByStoredFile((int) $row['id']) === null) {
                    $files[] = StoredFile::fromRow($row);
                }
            }
        }

        return ['folders' => $folders, 'files' => $files];
    }

    public function canChoose(User $user, UploadToken $token, StoredFile $file): bool
    {
        if (! self::enabled()) {
            return parent::canChoose($user, $token, $file);
        }

        if ($file->disk !== $token->disk) {
            return false;
        }

        $row = $this->files->findByStoredFile($file->id);

        if (is_array($row) && $row['deleted_at'] !== null) {
            return false;
        }

        $folder = is_array($row) ? (string) $row['folder'] : Spaces::parent($file->path);

        return $token->inRoots($folder) && $this->readable($user, $token, $folder);
    }

    public function discard(StoredFile $file): void
    {
        if (! self::enabled()) {
            parent::discard($file);

            return;
        }

        $row = $this->files->findByStoredFile($file->id);

        if (is_array($row)) {
            (new Trash())->purge((int) $row['id']);

            return;
        }

        parent::discard($file);
    }

    /**
     * Registers the folder and its parents with the space's default rights.
     */
    public function prepare(string $disk, string $folder, int $userId): void
    {
        $repository = $this->acl->folders();
        $space      = Spaces::of($disk, $folder);

        if ($space === Spaces::SHARED) {
            $repository->ensure($disk, 'shared', ['own_permissions' => true]);
        }

        foreach (array_reverse(Spaces::chain($folder)) as $path) {
            $owner = Spaces::personalOwner($disk, $path);

            if ($owner !== null && $path === Spaces::personalPath($owner)) {
                $repository->ensure($disk, $path, ['owner_id' => $owner, 'own_permissions' => true]);

                continue;
            }

            if ($disk === 'private' && preg_match(self::AVATAR_FOLDER, $path) === 1 && $repository->find($disk, $path) === null) {
                $created = $repository->ensure($disk, $path, ['owner_id' => $owner, 'created_by' => $userId]);
                $repository->savePermissions($created['id'], true, [
                    ['subject_type' => 'authenticated', 'subject_id' => '', 'rights' => [Rights::VIEW]],
                ]);

                continue;
            }

            if ($path !== 'users' && $path !== 'modules' && $path !== 'shared') {
                $repository->ensure($disk, $path, ['created_by' => $userId]);
            }
        }
    }

    /**
     * The field's own folder (the module chose it) or a folder whose ACL lets the user view it,
     * always inside the token's roots.
     */
    private function readable(User $user, UploadToken $token, string $path): bool
    {
        if (! $token->inRoots($path)) {
            return false;
        }

        return $token->inFolder($path) || $this->acl->can($user, $token->disk, $path, Rights::VIEW);
    }

    private static function enabled(): bool
    {
        try {
            return (new ModuleState())->isEnabled('Files');
        } catch (Throwable) {
            return false;
        }
    }
}
