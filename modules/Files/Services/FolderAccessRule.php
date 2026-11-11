<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Registry\ModuleState;
use Modules\Core\Storage\FileAccessRule;
use Modules\Core\Storage\OwnerAccess;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Policies\PolicyRegistry;
use Throwable;

/**
 * The private file rule of the Files module (`Config\Storage::$accessRule` through
 * Config/Registrar.php): who may read `files/private/{key}`.
 *
 * 1. Files module disabled: the Core's OwnerAccess (the uploader and superadmins).
 * 2. The file's folder (its `files.folder`, else the folder of its stored path) is inside the
 *    folder of a module's `FileAccessPolicy`: the policy decides (the file follows the
 *    visibility of the module's record). Superadmins always pass.
 * 3. Guests: nothing.
 * 4. A trashed file: only users who may delete in its folder (to restore it) see it.
 * 5. The folder is governed by the folder ACL: its `view` right decides.
 * 6. Anything else (a file outside every managed folder): OwnerAccess.
 */
final class FolderAccessRule implements FileAccessRule
{
    public function canView(?User $user, StoredFile $file): bool
    {
        try {
            if (! (new ModuleState())->isEnabled('Files')) {
                return (new OwnerAccess())->canView($user, $file);
            }

            $row    = (new FileRepository())->findByStoredFile($file->id);
            $folder = is_array($row) ? (string) $row['folder'] : Spaces::parent($file->path);
            $policy = PolicyRegistry::instance()->for($folder);

            if ($policy !== null) {
                return Authorization::isSuperadmin($user) || $policy->canView($user, $file);
            }

            if ($user === null || $user->id === null) {
                return false;
            }

            $acl = new FolderAcl();

            if (is_array($row) && $row['deleted_at'] !== null) {
                return $acl->can($user, $file->disk, $folder, Rights::DELETE);
            }

            if ($acl->governs($file->disk, $folder)) {
                return $acl->can($user, $file->disk, $folder, Rights::VIEW);
            }

            return (new OwnerAccess())->canView($user, $file);
        } catch (Throwable) {
            return false; // default deny
        }
    }
}
