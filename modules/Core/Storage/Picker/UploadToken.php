<?php

declare(strict_types=1);

namespace Modules\Core\Storage\Picker;

use Modules\Core\Security\SigningKey;

/**
 * The signed upload token of a file-picker field (docs/files.md, "File picker"): what one field
 * allows one user to do. The server issues it when it renders the field (`PickerField::token()`)
 * and accepts an upload or a browse request only with a valid token, so the user cannot change
 * the target folder, the disk, the types or the size.
 *
 * Format: `base64url(JSON payload) . '.' . base64url(HMAC-SHA256)`. The key is derived from
 * `encryption.key` (or `app.maskKey`) with a context of its own (`SigningKey`), so a token
 * is never valid anywhere else. Tokens are bound to one user and expire
 * (`Config\Storage::$pickerTokenTtl`).
 */
final class UploadToken
{
    public const MODES = ['upload', 'browse', 'both'];

    /**
     * @param list<string> $types allowed extensions (empty: the global list)
     * @param list<string> $roots folders the user may browse (`browse` / `both`), on `$disk`
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $mode,
        public readonly string $disk,
        public readonly string $folder,
        public readonly array $types,
        public readonly int $maxSize,
        public readonly int $maxFiles,
        public readonly array $roots,
        public readonly bool $reencode,
        public readonly int $expires,
    ) {
    }

    public function canUpload(): bool
    {
        return $this->mode === 'upload' || $this->mode === 'both';
    }

    public function canBrowse(): bool
    {
        return $this->mode === 'browse' || $this->mode === 'both';
    }

    /**
     * Whether a folder is the field's folder or one of its subfolders.
     */
    public function inFolder(string $path): bool
    {
        return self::within($path, $this->folder);
    }

    /**
     * Whether a folder is inside one of the browse roots (or the field's own folder).
     */
    public function inRoots(string $path): bool
    {
        foreach ([$this->folder, ...$this->roots] as $root) {
            if (self::within($path, $root)) {
                return true;
            }
        }

        return false;
    }

    public static function within(string $path, string $root): bool
    {
        return $root !== '' && ($path === $root || str_starts_with($path, $root . '/'));
    }

    public function encode(): string
    {
        $payload = self::base64((string) json_encode([
            'u' => $this->userId,
            'm' => $this->mode,
            'd' => $this->disk,
            'f' => $this->folder,
            't' => $this->types,
            's' => $this->maxSize,
            'n' => $this->maxFiles,
            'r' => $this->roots,
            'e' => $this->reencode,
            'x' => $this->expires,
        ], JSON_UNESCAPED_SLASHES));

        return $payload . '.' . self::base64(hash_hmac('sha256', $payload, self::key(), true));
    }

    /**
     * The token, or null when it is malformed, its signature is wrong or it has expired.
     */
    public static function decode(string $token, ?int $now = null): ?self
    {
        if (strlen($token) > 4096 || preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $token, $parts) !== 1) {
            return null;
        }

        $expected = self::base64(hash_hmac('sha256', $parts[1], self::key(), true));

        if (! hash_equals($expected, $parts[2])) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        if (! is_array($data) || ! is_int($data['u'] ?? null) || ! is_int($data['x'] ?? null) || ! in_array($data['m'] ?? null, self::MODES, true)
            || ! in_array($data['d'] ?? null, ['public', 'private'], true) || ! is_string($data['f'] ?? null)
            || ! is_array($data['t'] ?? null) || ! is_int($data['s'] ?? null) || ! is_int($data['n'] ?? null)
            || ! is_array($data['r'] ?? null) || ! is_bool($data['e'] ?? null)) {
            return null;
        }

        if ($data['x'] < ($now ?? time())) {
            return null;
        }

        return new self(
            $data['u'],
            $data['m'],
            $data['d'],
            $data['f'],
            array_values(array_map('strval', $data['t'])),
            $data['s'],
            $data['n'],
            array_values(array_map('strval', $data['r'])),
            $data['e'],
            $data['x'],
        );
    }

    private static function key(): string
    {
        return SigningKey::derive('upload-token');
    }

    private static function base64(string $raw): string
    {
        return SigningKey::base64($raw);
    }
}
