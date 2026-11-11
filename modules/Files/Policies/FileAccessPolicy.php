<?php

declare(strict_types=1);

namespace Modules\Files\Policies;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\StoredFile;

/**
 * Who may read the private files of a module's folder, when they follow the visibility of the
 * module's records instead of the folder ACL (e.g. the attachment of a private event). A module
 * registers its policy for its folder (`modules/<module>` or below) in its Config/Events.php:
 *
 * ```php
 * Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
 *     $registry->register(new AttachmentPolicy());
 * });
 * ```
 *
 * The policy decides alone for the files of its folder (default deny: return false when unsure).
 */
interface FileAccessPolicy
{
    /** The folder it governs (both disks), e.g. `modules/events`. */
    public function folder(): string;

    /**
     * How the folder appears in the file manager: `hidden` (default) or `readonly` (listed,
     * without changes, for users with `files.all`).
     */
    public function browse(): string;

    /**
     * Whether the user (null: a guest) may read the private file.
     */
    public function canView(?User $user, StoredFile $file): bool;
}
