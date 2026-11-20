<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use RuntimeException;

/**
 * Voluntary Application Server Identification (VAPID, RFC 8292) with PHP's OpenSSL only
 * (D-44-01): a JWT signed with ES256 (ECDSA P-256 + SHA-256, RFC 7515/7518) whose `aud` is the
 * origin of the push service, `exp` at most 24 hours ahead and `sub` the site's contact
 * (`mailto:` or `https:`), sent as `Authorization: vapid t=<JWT>, k=<public key>`.
 *
 * Sources: RFC 8292 sections 2, 2.2, 3 and 3.1; RFC 7518 section 3.4 (the signature is R || S,
 * 64 bytes, not OpenSSL's DER).
 */
final class Vapid
{
    /** Longest validity allowed by RFC 8292 section 2. */
    public const MAX_LIFETIME = 86400;

    /** Validity used: 12 hours (push services may refuse a token close to 24 h). */
    public const LIFETIME = 43200;

    /**
     * @param string $publicKey  raw uncompressed point (65 bytes)
     * @param string $privateKey d (32 bytes)
     */
    public function __construct(
        private readonly string $publicKey,
        private readonly string $privateKey,
        private readonly string $subject,
    ) {
    }

    /**
     * The `Authorization` header for a push service endpoint.
     */
    public function authorization(string $endpoint, ?int $now = null): string
    {
        return 'vapid t=' . $this->token(self::audience($endpoint), ($now ?? time()) + self::LIFETIME) . ', k=' . Base64Url::encode($this->publicKey);
    }

    public function token(string $audience, int $expires): string
    {
        $header = Base64Url::encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = Base64Url::encode((string) json_encode(['aud' => $audience, 'exp' => $expires, 'sub' => $this->subject], JSON_UNESCAPED_SLASHES));
        $input  = $header . '.' . $claims;

        if (! openssl_sign($input, $der, EcKey::privateKey($this->privateKey, $this->publicKey), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('ES256 signing failed.');
        }

        return $input . '.' . Base64Url::encode(self::derToRaw($der));
    }

    /**
     * Verifies a JWT's ES256 signature with a raw public key (tests and diagnostics).
     */
    public static function verify(string $jwt, string $publicKey): bool
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        $raw = Base64Url::decode($parts[2]);

        if ($raw === null || strlen($raw) !== 64 || ! EcKey::isPublicKey($publicKey)) {
            return false;
        }

        return openssl_verify($parts[0] . '.' . $parts[1], self::rawToDer($raw), EcKey::publicKey($publicKey), OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * The origin of a push endpoint (`https://host[:port]`), the JWT's `aud`.
     */
    public static function audience(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Invalid push endpoint.');
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * DER ECDSA signature (SEQUENCE of two INTEGERs) to R || S (32 bytes each).
     */
    public static function derToRaw(string $der): string
    {
        $offset = 2;

        if (ord($der[0]) !== 0x30) {
            throw new RuntimeException('Invalid DER signature.');
        }

        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $raw = '';

        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$offset]) !== 0x02) {
                throw new RuntimeException('Invalid DER signature.');
            }

            $length  = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\0");
            $raw    .= str_pad($integer, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }

    /**
     * R || S to a DER ECDSA signature.
     */
    public static function rawToDer(string $raw): string
    {
        $integers = '';

        foreach (str_split($raw, 32) as $part) {
            $part = ltrim($part, "\0");

            if ($part === '' || (ord($part[0]) & 0x80) !== 0) {
                $part = "\0" . $part;
            }

            $integers .= "\x02" . chr(strlen($part)) . $part;
        }

        return "\x30" . chr(strlen($integers)) . $integers;
    }
}
