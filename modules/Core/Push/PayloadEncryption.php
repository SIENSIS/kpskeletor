<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use RuntimeException;

/**
 * Message encryption for Web Push (RFC 8291) with the `aes128gcm` content coding (RFC 8188),
 * implemented with PHP's OpenSSL only (D-44-01):
 *
 * 1. a new P-256 key pair per message (the application server's `as_public`) and ECDH with the
 *    subscription's `p256dh` (the user agent's `ua_public`);
 * 2. `IKM = HKDF(auth_secret, ecdh_secret, "WebPush: info" || 0x00 || ua_public || as_public, 32)`;
 * 3. with a random 16-byte salt: `CEK = HKDF(salt, IKM, "Content-Encoding: aes128gcm" || 0x00, 16)`
 *    and `NONCE = HKDF(salt, IKM, "Content-Encoding: nonce" || 0x00, 12)`;
 * 4. one record: AES-128-GCM of `payload || 0x02` (last-record delimiter), tag appended;
 * 5. body = `salt || rs (uint32) || idlen (65) || as_public || ciphertext`.
 *
 * Sources: RFC 8291 sections 3.3, 3.4 and 4 and its appendix A (the test vectors used by
 * `PushCryptoTest`), RFC 8188 section 2, RFC 5869 (HKDF).
 */
final class PayloadEncryption
{
    /** Record size written in the header (one record is used; RFC 8291 section 4). */
    public const RECORD_SIZE = 4096;

    /** Largest payload: 4096 - 16 (tag) - 1 (delimiter) - 86 (header) leaves room for 3993 bytes. */
    public const MAX_PAYLOAD = 3993;

    /**
     * @param string      $payload   plain text (UTF-8 JSON in practice)
     * @param string      $uaPublic  the subscription's `p256dh` (raw, 65 bytes)
     * @param string      $authSecret the subscription's `auth` (raw, 16 bytes)
     * @param array{public: string, private: string}|null $serverKeys fixed keys (tests); default: new ones
     * @param string|null $salt      fixed salt (tests); default: 16 random bytes
     *
     * @return string the request body
     */
    public static function encrypt(string $payload, string $uaPublic, string $authSecret, ?array $serverKeys = null, ?string $salt = null): string
    {
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new RuntimeException('The push payload is too long.');
        }

        if (strlen($authSecret) !== 16) {
            throw new RuntimeException('Invalid push auth secret.');
        }

        $serverKeys ??= EcKey::generate();
        $salt       ??= random_bytes(16);

        if (strlen($salt) !== 16) {
            throw new RuntimeException('Invalid salt.');
        }

        $ecdh = EcKey::sharedSecret(EcKey::privateKey($serverKeys['private'], $serverKeys['public']), EcKey::publicKey($uaPublic));
        [$cek, $nonce] = self::keys($ecdh, $authSecret, $uaPublic, $serverKeys['public'], $salt);

        $tag        = '';
        $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM encryption failed.');
        }

        return $salt . pack('N', self::RECORD_SIZE) . chr(strlen($serverKeys['public'])) . $serverKeys['public'] . $ciphertext . $tag;
    }

    /**
     * Decrypts a body with the user agent's keys (what the browser does; used by the tests).
     *
     * @param string $uaPrivate d (32 bytes)
     */
    public static function decrypt(string $body, string $uaPublic, string $uaPrivate, string $authSecret): string
    {
        if (strlen($body) < 21 + 65 + 17) {
            throw new RuntimeException('The body is too short.');
        }

        $salt     = substr($body, 0, 16);
        $idLength = ord($body[20]);
        $asPublic = substr($body, 21, $idLength);
        $record   = substr($body, 21 + $idLength);

        $ecdh = EcKey::sharedSecret(EcKey::privateKey($uaPrivate, $uaPublic), EcKey::publicKey($asPublic));
        [$cek, $nonce] = self::keys($ecdh, $authSecret, $uaPublic, $asPublic, $salt);

        $plain = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($record, -16));

        if ($plain === false) {
            throw new RuntimeException('Decryption failed.');
        }

        $plain = rtrim($plain, "\0");

        if (! str_ends_with($plain, "\x02")) {
            throw new RuntimeException('Missing record delimiter.');
        }

        return substr($plain, 0, -1);
    }

    /**
     * HKDF-SHA256 (RFC 5869) for an output of at most 32 bytes (one block).
     */
    public static function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $length);
    }

    /**
     * @return array{0: string, 1: string} CEK, NONCE
     */
    private static function keys(string $ecdh, string $authSecret, string $uaPublic, string $asPublic, string $salt): array
    {
        $ikm = self::hkdf($authSecret, $ecdh, "WebPush: info\x00" . $uaPublic . $asPublic, 32);

        return [
            self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16),
            self::hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12),
        ];
    }
}
