<?php

declare(strict_types=1);

namespace Modules\Core\Push;

/**
 * Base64url without padding (RFC 4648 section 5), the encoding of Web Push keys and JWTs.
 */
final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @return string|null The bytes, or null when the text is not base64url
     */
    public static function decode(string $text): ?string
    {
        $text = trim($text);

        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $text) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr(rtrim($text, '='), '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
