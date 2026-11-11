<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use CodeIgniter\HTTP\Files\UploadedFile;
use Kpcrudy\Storage\UploadHandler;
use Kpcrudy\Storage\UploadRejectedException;

/**
 * Base of the kpcrudy upload handlers of module fields (News cover, Pages featured image…): the
 * file goes through the Core's Storage service (allow-list, MIME check, re-encoding, random name,
 * `stored_files`) into one folder of one disk; the column keeps the file key. kpcrudy never
 * writes the file itself. Extracted from News in session 20.
 *
 * TODO(session 23): fields move to the Core file picker (`callbackField()`).
 */
abstract class CrudUploadHandler implements UploadHandler
{
    /** Allowed extensions, comma separated (also given to kpcrudy's `allowed` option). */
    public const TYPES = 'jpg,jpeg,png,webp';

    /** Largest file, in bytes (the global `Core.uploadMaxSize` still wins when smaller). */
    public const MAX_SIZE = 5 * 1024 * 1024;

    final public function __construct()
    {
    }

    /** Folder of the Storage service (e.g. `modules/news`). */
    abstract protected function folder(): string;

    protected function disk(): string
    {
        return 'public';
    }

    public function store(UploadedFile $file, string $field, array $context): string
    {
        try {
            return service('storage')->store($file, $this->disk(), $this->folder(), UploadRules::make(static::TYPES, static::MAX_SIZE))->key;
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
        return static::urlOf($stored);
    }

    /**
     * The public URL of a stored file key, or null.
     */
    public static function urlOf(?string $key): ?string
    {
        $file = $key !== null && $key !== '' ? service('storage')->find($key) : null;

        return $file !== null ? service('storage')->url($file) : null;
    }

    /**
     * kpcrudy options of the field (`fieldType($field, 'image', static::fieldOptions())`).
     *
     * @return array<string, mixed>
     */
    public static function fieldOptions(): array
    {
        return ['handler' => new static(), 'allowed' => static::TYPES, 'maxSize' => intdiv(static::MAX_SIZE, 1024)];
    }
}
