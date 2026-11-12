<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Files\File;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\UploadRefused;
use RuntimeException;

/**
 * Uploads into a folder of the file manager: the folder ACL (`upload`, capped by `files.upload`),
 * never a module folder, the quotas of the folder's chain, then the Storage service (upload rules,
 * re-encoding, random name), the `files` row, the thumbnail and the activity log.
 */
final class Uploader
{
    public function __construct(
        private readonly FolderAcl $acl = new FolderAcl(),
        private readonly QuotaService $quotas = new QuotaService(),
        private readonly FileRepository $files = new FileRepository(),
        private readonly ThumbnailService $thumbnails = new ThumbnailService(),
    ) {
    }

    /**
     * @return int the `files` id
     *
     * @throws UploadRefused    the Storage service refused the file (already in the security log)
     * @throws RuntimeException no right or quota exceeded (translated message)
     */
    public function upload(User $user, string $disk, string $folder, UploadedFile|File $upload, ?string $name = null): int
    {
        if (Spaces::of($disk, $folder) === Spaces::MODULES || ! $this->acl->managerCan($user, $disk, $folder, Rights::UPLOAD)) {
            throw new RuntimeException(lang('Files.errors.forbidden'));
        }

        $this->quotas->check($disk, $folder, max(0, (int) $upload->getSize()));

        /** @var Storage $storage */
        $storage = service('storage');
        $stored  = $storage->store($upload, $disk, $folder, null, $name);

        // Re-encoding can change the size: check again with the stored size.
        try {
            $this->quotas->check($disk, $folder, $stored->size - max(0, (int) $upload->getSize()));
        } catch (RuntimeException $e) {
            $storage->delete($stored);

            throw $e;
        }

        $id = $this->files->create($stored, $folder, $this->thumbnails->make($stored));
        activity_log('Files', 'file_uploaded', ['resource' => 'files', 'record_id' => (string) $id, 'summary' => $disk . ':' . $folder . '/' . $stored->originalName]);

        return $id;
    }
}
