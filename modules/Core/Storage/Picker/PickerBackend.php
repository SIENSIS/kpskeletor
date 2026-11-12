<?php

declare(strict_types=1);

namespace Modules\Core\Storage\Picker;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\StoredFile;
use Modules\Core\Storage\UploadRefused;
use RuntimeException;

/**
 * What the file picker's endpoints do with a valid token (`Config\Storage::$pickerBackend`). The
 * Core's `StorageBackend` stores into the token's folder and lets users choose their own uploads
 * of that folder; the Files module replaces it (folders created on demand with the space's rights,
 * quotas, `files` rows, browsing limited by the browse roots and the folder ACL).
 */
interface PickerBackend
{
    /**
     * Stores an upload into the token's folder (the token was checked: user, mode, expiry).
     *
     * @throws UploadRefused    the Storage service refused the file
     * @throws RuntimeException refused for another reason (translated message: quota…)
     */
    public function upload(User $user, UploadToken $token, UploadedFile $file): StoredFile;

    /**
     * The folders and files the user may choose from in a folder inside the token's roots, or
     * null when the folder cannot be browsed.
     *
     * @return array{folders: list<array{path: string, name: string}>, files: list<StoredFile>}|null
     */
    public function browse(User $user, UploadToken $token, string $path): ?array;

    /**
     * Whether the user may use this file as the value of the field (uploaded through it or
     * chosen inside its roots).
     */
    public function canChoose(User $user, UploadToken $token, StoredFile $file): bool;

    /**
     * The thumbnail URL of an image, if the backend has one.
     */
    public function thumbnailUrl(StoredFile $file): ?string;

    /**
     * Removes a file the field no longer uses (e.g. a replaced avatar).
     */
    public function discard(StoredFile $file): void;
}
