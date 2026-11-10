<?php

declare(strict_types=1);

namespace Modules\News\Storage;

use CodeIgniter\HTTP\Files\UploadedFile;
use Kpcrudy\Storage\UploadHandler;
use Kpcrudy\Storage\UploadRejectedException;
use Modules\Core\Storage\UploadRefused;
use Modules\Core\Storage\UploadRules;

/**
 * kpcrudy upload handler of the post cover: the file goes through the Core's Storage service
 * (allow-list, MIME check, re-encoding, random name, `stored_files`) on the public disk, folder
 * `modules/news`; the column keeps the file key. kpcrudy never writes the file itself.
 *
 * TODO(session 23): replace this field with the Core file picker (`callbackField()`), which
 * also lets authors reuse files of the Files module.
 */
final class CoverUploads implements UploadHandler
{
    public const TYPES = 'jpg,jpeg,png,webp';

    /** Largest cover, in bytes (the global `Core.uploadMaxSize` still wins when smaller). */
    public const MAX_SIZE = 5 * 1024 * 1024;

    public function store(UploadedFile $file, string $field, array $context): string
    {
        try {
            return service('storage')->store($file, 'public', 'modules/news', UploadRules::make(self::TYPES, self::MAX_SIZE))->key;
        } catch (UploadRefused $refused) {
            throw new UploadRejectedException($refused->getMessage());
        }
    }

    public function delete(string $stored, string $field, array $context): void
    {
        $file = service('storage')->find($stored);

        if ($file !== null) {
            service('storage')->delete($file);
        }
    }

    public function url(string $stored, string $field, array $context): ?string
    {
        return self::urlOf($stored);
    }

    /**
     * The public URL of a cover key, or null.
     */
    public static function urlOf(?string $key): ?string
    {
        $file = $key !== null && $key !== '' ? service('storage')->find($key) : null;

        return $file !== null ? service('storage')->url($file) : null;
    }
}
