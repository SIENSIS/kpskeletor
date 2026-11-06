<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use CodeIgniter\Shield\Entities\User;

/**
 * Decides who may read a private file through `files/private/{key}` (Config\Storage::$accessRule).
 * The Core's rule is OwnerAccess; the Files module (session 22) replaces it with the folder
 * permissions and the `FileAccessPolicy` of the module that owns a folder. Default deny.
 */
interface FileAccessRule
{
    public function canView(?User $user, StoredFile $file): bool;
}
