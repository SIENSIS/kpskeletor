<?php

declare(strict_types=1);

namespace Modules\Files\Config;

use Modules\Files\Services\FolderAccessRule;
use Modules\Files\Services\PickerBackend;

/**
 * Configuration the Files module contributes (CodeIgniter registrar): the rule of the Storage
 * service's private files (`files/private/{key}`) becomes the folder ACL and the modules'
 * `FileAccessPolicy`s (D-13-01). While the module is disabled the rule behaves like the Core's
 * `OwnerAccess` (the uploader and superadmins).
 *
 * The file picker's backend (`Config\Storage::$pickerBackend`) becomes `PickerBackend`: folders
 * created on demand, quotas, `files` rows, browsing limited by the folder ACL (the Core's backend
 * while the module is disabled).
 */
final class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Storage(): array
    {
        return ['accessRule' => FolderAccessRule::class, 'pickerBackend' => PickerBackend::class];
    }
}
