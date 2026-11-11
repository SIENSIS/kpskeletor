<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Storage\Storage;

/**
 * The trash: deleting a file only marks it (`deleted_at`, `deleted_by`); it keeps counting for
 * quotas until it is purged. Restoring clears the mark (the folder still exists: a folder with
 * files, trashed ones included, cannot be deleted). Purging deletes the stored file (Storage
 * service: disk and `stored_files`), its thumbnail and its row.
 */
final class Trash
{
    public function __construct(
        private readonly ?Storage $storage = null,
        private readonly ThumbnailService $thumbnails = new ThumbnailService(),
    ) {
    }

    public function trash(int $fileId, ?int $userId): void
    {
        db_connect()->table('files')->where('id', $fileId)->where('deleted_at', null)
            ->update(['deleted_at' => Time::now()->toDateTimeString(), 'deleted_by' => $userId]);
    }

    public function restore(int $fileId): void
    {
        db_connect()->table('files')->where('id', $fileId)->update(['deleted_at' => null, 'deleted_by' => null]);
    }

    /**
     * Deletes the file for good (disk and records).
     */
    public function purge(int $fileId): void
    {
        $row = db_connect()->table('files')->where('id', $fileId)->get()->getRowArray();

        if (! is_array($row)) {
            return;
        }

        $storage = $this->storage ?? service('storage');
        $stored  = $storage->findById((int) $row['stored_file_id']);
        $this->thumbnails->delete(is_string($row['thumbnail'] ?? null) ? $row['thumbnail'] : null);
        db_connect()->table('files')->where('id', $fileId)->delete();

        if ($stored !== null) {
            $storage->delete($stored);
        }
    }
}
