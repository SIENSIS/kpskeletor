<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Security\SigningKey;
use Modules\Core\Storage\Picker\PickerField;

/**
 * Avatars (docs/specification.md "Auth", docs/files.md "Avatars"): the profile's file-picker field
 * (upload only, private disk, the user's `users/{user_id}/avatar` folder, JPG/PNG/WebP up to 2 MB,
 * re-encoded), `user_profiles.avatar` = the file key, served at `files/avatar/{key}` to signed-in
 * users (or with a signed URL, for API clients) with long cache headers: the URL is versioned by
 * the key itself (a new avatar is a new file and a new URL).
 */
final class Avatars
{
    public const TYPES    = ['jpg', 'jpeg', 'png', 'webp'];
    public const MAX_SIZE = 2 * 1024 * 1024;
    public const FOLDER   = 'users/{user_id}/avatar';

    public static function field(): PickerField
    {
        return PickerField::make('avatar')->mode('upload')->disk('private')->folder(self::FOLDER)
            ->types(self::TYPES)->maxSize(self::MAX_SIZE)->maxFiles(1);
    }

    /**
     * The key of a user's avatar, or null.
     */
    public static function keyOf(int $userId): ?string
    {
        $row = db_connect()->table('user_profiles')->select('avatar')->where('user_id', $userId)->get()->getRowArray();
        $key = is_array($row) ? $row['avatar'] : null;

        return is_string($key) && PickerField::keys($key) === [$key] ? $key : null;
    }

    /**
     * Whether a key is someone's current avatar.
     */
    public static function isAvatar(string $key): bool
    {
        return PickerField::keys($key) === [$key]
            && db_connect()->table('user_profiles')->where('avatar', $key)->countAllResults() > 0;
    }

    /**
     * The URL of an avatar for signed-in users (null: no avatar).
     */
    public static function url(?string $key): ?string
    {
        return $key !== null && PickerField::keys($key) === [$key] ? site_url('files/avatar/' . $key) : null;
    }

    public static function urlFor(User|int $user): ?string
    {
        return self::url(self::keyOf($user instanceof User ? (int) $user->id : $user));
    }

    /**
     * A URL that works without a session until it expires (API clients: `me.avatar_url`).
     */
    public static function signedUrl(?string $key, int $ttl = HOUR, ?int $now = null): ?string
    {
        $url = self::url($key);

        if ($url === null || $key === null) {
            return null;
        }

        $expires = ($now ?? time()) + max(60, $ttl);

        return $url . '?expires=' . $expires . '&signature=' . self::signature($key, $expires);
    }

    public static function validSignature(string $key, string $expires, string $signature, ?int $now = null): bool
    {
        return ctype_digit($expires) && (int) $expires >= ($now ?? time())
            && hash_equals(self::signature($key, (int) $expires), $signature);
    }

    private static function signature(string $key, int $expires): string
    {
        return SigningKey::base64(hash_hmac('sha256', $key . '|' . $expires, SigningKey::derive('avatar-url'), true));
    }
}
