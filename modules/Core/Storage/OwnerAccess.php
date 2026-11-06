<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;

/**
 * The Core's private file rule until the folder permissions (session 22): the user who uploaded
 * the file and superadmins. Guests and everybody else get nothing.
 */
final class OwnerAccess implements FileAccessRule
{
    public function canView(?User $user, StoredFile $file): bool
    {
        if ($user === null || $user->id === null) {
            return false;
        }

        if (Authorization::isSuperadmin($user)) {
            return true;
        }

        return $file->uploadedBy !== null && $file->uploadedBy === (int) $user->id;
    }
}
