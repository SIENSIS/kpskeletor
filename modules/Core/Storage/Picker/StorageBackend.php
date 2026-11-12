<?php

declare(strict_types=1);

namespace Modules\Core\Storage\Picker;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StoredFile;
use Modules\Core\Storage\UploadRules;

/**
 * The Core's picker backend (used while no module provides one): uploads go through the Storage
 * service into the token's folder; a user may only choose the files they uploaded into that
 * folder.
 */
class StorageBackend implements PickerBackend
{
    public function upload(User $user, UploadToken $token, UploadedFile $file): StoredFile
    {
        return $this->storage()->store($file, $token->disk, $token->folder, self::rules($token));
    }

    public function browse(User $user, UploadToken $token, string $path): ?array
    {
        if ($path !== $token->folder) {
            return null;
        }

        $rows = db_connect()->table('stored_files')
            ->where('disk', $token->disk)
            ->where('uploaded_by', (int) $user->id)
            ->like('path', $token->folder . '/', 'after')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get()->getResultArray();

        $files = [];

        foreach ($rows as $row) {
            $file = StoredFile::fromRow($row);

            if (dirname($file->path) === $token->folder) {
                $files[] = $file;
            }
        }

        return ['folders' => [], 'files' => $files];
    }

    public function canChoose(User $user, UploadToken $token, StoredFile $file): bool
    {
        return $file->disk === $token->disk
            && $token->inFolder(dirname($file->path))
            && $file->uploadedBy !== null && $file->uploadedBy === (int) $user->id;
    }

    public function thumbnailUrl(StoredFile $file): ?string
    {
        return null;
    }

    public function discard(StoredFile $file): void
    {
        $this->storage()->delete($file);
    }

    public static function rules(UploadToken $token): UploadRules
    {
        return new UploadRules($token->types, $token->maxSize > 0 ? $token->maxSize : null, $token->reencode);
    }

    protected function storage(): Storage
    {
        /** @var Storage */
        return service('storage');
    }
}
