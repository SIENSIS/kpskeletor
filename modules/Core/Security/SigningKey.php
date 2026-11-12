<?php

declare(strict_types=1);

namespace Modules\Core\Security;

/**
 * Keys for the application's own signatures (upload tokens, signed avatar URLs…): derived with
 * HKDF from `encryption.key` (or `app.maskKey` when there is none) and a context of their own, so
 * a signature made for one purpose is never valid for another. Without any secret (production
 * refuses to boot without `app.maskKey`) the key is random for this process: nothing can be
 * forged, signatures only live for the request.
 */
final class SigningKey
{
    private static ?string $random = null;

    public static function derive(string $context): string
    {
        $secret = config('Encryption')->key;

        if ($secret === '') {
            $secret = MaskKey::value();
        }

        if ($secret === '') {
            $secret = self::$random ??= random_bytes(32);
        }

        return hash_hkdf('sha256', $secret, 32, 'kpskeletor.' . $context);
    }

    /**
     * Base64url without padding.
     */
    public static function base64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
