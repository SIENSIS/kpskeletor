<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Storage\UploadHandler;
use Kpcrudy\Storage\UploadRejectedException;
use Modules\Core\Storage\Picker\PickerField;

/**
 * Base of the kpcrudy upload handlers of module fields (News cover, Pages featured image…): the
 * file goes through the Core's Storage service (allow-list, MIME check, re-encoding, random name,
 * `stored_files`) into one folder of one disk; the column keeps the file key. kpcrudy never
 * writes the file itself. Extracted from News in session 20.
 *
 * Since session 23 the same class describes the field as a **file picker** (upload or choose, in
 * the module's folder and the shared folders the user may view): `pickerField()`,
 * `renderPicker()` for kpcrudy's `callbackField()`, `accepts()` in the `beforeInsert` /
 * `beforeUpdate` hooks (News cover, Pages featured image). The kpcrudy upload handler stays for
 * modules that still use an `image` field.
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
     * The file-picker field: upload or choose, the class's types and size, the module's folder;
     * on the public disk the user may also choose from the public shared folders the folder ACL
     * lets them view.
     */
    public static function pickerField(string $name): PickerField
    {
        $handler = new static();
        $folder  = $handler->folder();
        $roots   = $handler->disk() === 'public' ? [$folder, 'shared'] : [$folder];

        return PickerField::make($name)->mode('both')->disk($handler->disk())->folder($folder)
            ->types(static::TYPES)->maxSize(static::MAX_SIZE)->maxFiles(1)->browseRoot($roots);
    }

    /**
     * The picker's HTML for kpcrudy's `callbackField()`:
     * `->callbackField('cover', static fn ($value, ?array $row, array $field): string => CoverUploads::renderPicker('cover', $value, $field))`.
     *
     * @param array<string, mixed> $field
     */
    public static function renderPicker(string $name, mixed $value, array $field = []): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $error = $field['error'] ?? null;

        return static::pickerField($name)->render($user, is_string($value) ? $value : null, [
            'id'    => 'field-' . $name,
            'error' => is_string($error) && $error !== '' ? $error : null,
        ]);
    }

    /**
     * Whether the posted key can be saved (kept, uploaded through the field or chosen in its
     * roots): call it in the `beforeInsert` / `beforeUpdate` hooks.
     */
    public static function accepts(string $name, mixed $value, ?string $current = null): bool
    {
        $user = auth()->user();

        return $user instanceof User && static::pickerField($name)->accepts($user, is_string($value) ? $value : null, $current);
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
