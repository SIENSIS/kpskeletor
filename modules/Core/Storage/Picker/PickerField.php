<?php

declare(strict_types=1);

namespace Modules\Core\Storage\Picker;

use CodeIgniter\Shield\Entities\User;
use Config\Storage as StorageConfig;
use Modules\Core\Storage\PathNormalizer;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StoredFile;
use Modules\Core\Storage\UploadRules;

/**
 * A file-picker field (docs/files.md, "File picker"; component `core/forms/file-picker`). A module
 * declares how the field works and renders it; the value posted with the form is the key of the
 * chosen file (several keys separated by commas when `maxFiles` > 1), which the module checks
 * with `accepts()` before saving.
 *
 * ```php
 * $field = PickerField::make('avatar')->mode('upload')->disk('private')
 *     ->folder('users/{user_id}/avatar')->types('jpg,png,webp')->maxSize(2 * 1024 * 1024);
 * echo $field->render($user, $profile['avatar']);
 * if (! $field->accepts($user, $posted, $profile['avatar'])) { … }
 * ```
 *
 * Variables of `folder` and `browseRoot`: `{user_id}`. The folder and the roots are resolved on
 * the server and signed into the field's upload token, with the types, the size and an expiry.
 */
final class PickerField
{
    public const COMPONENT = 'core/forms/file-picker';

    /** Image types of rich text and covers. */
    public const IMAGES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private string $mode = 'upload';
    private string $disk = 'public';
    private string $folder = '';

    /** @var list<string> */
    private array $types = [];

    private int $maxSize = 0;
    private int $maxFiles = 1;

    /** @var list<string> */
    private array $roots = [];

    private bool $reencode = true;

    public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function mode(string $mode): self
    {
        $this->mode = in_array($mode, UploadToken::MODES, true) ? $mode : 'upload';

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->disk = $disk === 'private' ? 'private' : 'public';

        return $this;
    }

    public function folder(string $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @param list<string>|string $types
     */
    public function types(array|string $types): self
    {
        $this->types = UploadRules::parseTypes($types);

        return $this;
    }

    /** Largest file in bytes (the global `Core.uploadMaxSize` still wins when smaller). */
    public function maxSize(int $bytes): self
    {
        $this->maxSize = max(0, $bytes);

        return $this;
    }

    public function maxFiles(int $count): self
    {
        $this->maxFiles = max(1, min(50, $count));

        return $this;
    }

    /**
     * @param list<string>|string $roots folders on the field's disk the user may choose from
     */
    public function browseRoot(array|string $roots): self
    {
        $this->roots = array_values(array_filter(is_string($roots) ? [$roots] : $roots, static fn (string $root): bool => $root !== ''));

        return $this;
    }

    public function reencode(bool $reencode): self
    {
        $this->reencode = $reencode;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function allowedTypes(): array
    {
        return $this->types;
    }

    public function getMaxFiles(): int
    {
        return $this->maxFiles;
    }

    /**
     * What this field allows this user (variables resolved, paths normalised), with a new expiry.
     */
    public function scope(User $user, ?int $now = null): UploadToken
    {
        $resolve = static fn (string $path): string => PathNormalizer::normalize(str_replace('{user_id}', (string) (int) $user->id, $path));
        $roots   = [];

        foreach ($this->roots as $root) {
            $roots[] = $resolve($root);
        }

        return new UploadToken(
            (int) $user->id,
            $this->mode,
            $this->disk,
            $resolve($this->folder),
            $this->types,
            $this->maxSize,
            $this->maxFiles,
            array_values(array_unique($roots)),
            $this->reencode,
            ($now ?? time()) + max(60, (int) config(StorageConfig::class)->pickerTokenTtl),
        );
    }

    /**
     * Options of the `core/forms/file-picker` component (and of the editor's "Insert image").
     *
     * @return array<string, mixed>
     */
    public function options(User $user): array
    {
        $types = self::storage()->validator()->allowedTypes($this->rules());

        helper('asset');

        return [
            'name'     => $this->name,
            'css'      => asset('core::css/file-picker.css'),
            'token'    => $this->scope($user)->encode(),
            'mode'     => $this->mode,
            'upload'   => site_url('files/picker/upload'),
            'browse'   => site_url('files/picker/browse'),
            'accept'   => $types === [] ? '' : implode(',', array_map(static fn (string $type): string => '.' . $type, $types)),
            'types'    => $types,
            'maxSize'  => $this->effectiveMaxSize(),
            'maxFiles' => $this->maxFiles,
        ];
    }

    /**
     * The field's HTML (theme component `file_picker`): a hidden input with the value, the current
     * file(s) and the upload / choose buttons.
     *
     * @param array{id?: string, label?: string, help?: string, invalid?: bool, disabled?: bool, error?: string} $settings
     */
    public function render(User $user, ?string $value, array $settings = []): string
    {
        $items = [];

        foreach (self::keys($value) as $key) {
            $item = self::describe($key);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return theme_component('file_picker', [
            'name'     => $this->name,
            'id'       => $settings['id'] ?? 'picker-' . preg_replace('/[^a-z0-9_-]+/i', '-', $this->name),
            'value'    => implode(',', array_column($items, 'key')),
            'items'    => $items,
            'mode'     => $this->mode,
            'multiple' => $this->maxFiles > 1,
            'options'  => ($settings['disabled'] ?? false) ? null : $this->options($user),
            'label'    => $settings['label'] ?? null,
            'help'     => $settings['help'] ?? null,
            'error'    => $settings['error'] ?? null,
            'disabled' => $settings['disabled'] ?? false,
        ]);
    }

    /**
     * Whether the posted value can be saved: at most `maxFiles` keys, each one kept from the
     * current value or a file this user may choose through this field (uploaded with its token
     * or inside its roots, of an allowed type). An empty value is accepted (no file).
     */
    public function accepts(User $user, ?string $value, ?string $current = null): bool
    {
        $keys  = self::keys($value);
        $parts = array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $part): bool => $part !== '');

        // Anything that is not a key, a repeated key or too many files: refused.
        if (count($parts) !== count($keys) || count($keys) > $this->maxFiles) {
            return false;
        }

        $kept    = self::keys($current);
        $scope   = $this->scope($user);
        $backend = self::backend();

        foreach ($keys as $key) {
            if (in_array($key, $kept, true)) {
                continue;
            }

            $file = self::storage()->find($key);

            if ($file === null || ($this->types !== [] && ! in_array($file->extension, $this->types, true)) || ! $backend->canChoose($user, $scope, $file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The file keys of a value (`key` or `key,key`); anything that is not a key is dropped.
     *
     * @return list<string>
     */
    public static function keys(?string $value): array
    {
        $keys = [];

        foreach (explode(',', (string) $value) as $key) {
            $key = trim($key);

            if (preg_match('/^[a-f0-9]{32}$/', $key) === 1 && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * What the component shows of a file (and what the endpoints return).
     *
     * @return array{key: string, name: string, url: string, thumbnail: string|null, size: int, mime: string, image: bool, width: int|null, height: int|null}|null
     */
    public static function describe(string|StoredFile $file): ?array
    {
        $file = is_string($file) ? self::storage()->find($file) : $file;

        if ($file === null) {
            return null;
        }

        $url = self::storage()->url($file);

        return [
            'key'       => $file->key,
            'name'      => $file->originalName,
            'url'       => $url,
            'thumbnail' => $file->isImage() ? (self::backend()->thumbnailUrl($file) ?? $url) : null,
            'size'      => $file->size,
            'mime'      => $file->mime,
            'image'     => $file->isImage(),
            'width'     => $file->width,
            'height'    => $file->height,
        ];
    }

    /**
     * The configured backend (`Config\Storage::$pickerBackend`); the Core's when it is not a
     * PickerBackend.
     */
    public static function backend(): PickerBackend
    {
        $class = config(StorageConfig::class)->pickerBackend;

        return is_a($class, PickerBackend::class, true) ? new $class() : new StorageBackend();
    }

    private function effectiveMaxSize(): int
    {
        return self::storage()->validator()->maxSize($this->rules());
    }

    private function rules(): UploadRules
    {
        return new UploadRules($this->types, $this->maxSize > 0 ? $this->maxSize : null, $this->reencode);
    }

    private static function storage(): Storage
    {
        /** @var Storage */
        return service('storage');
    }
}
