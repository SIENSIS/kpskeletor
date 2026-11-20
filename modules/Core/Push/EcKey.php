<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * P-256 (prime256v1) keys as Web Push writes them, converted to and from what PHP's OpenSSL
 * extension reads: a public key is the uncompressed point (65 bytes, 0x04 || X || Y), a private
 * key the 32-byte scalar d (RFC 8291 section 3.1, RFC 8292 section 3.2).
 */
final class EcKey
{
    /** DER prefix of a SubjectPublicKeyInfo for an uncompressed P-256 point (RFC 5480). */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** DER pieces of an ECPrivateKey for P-256 (RFC 5915): version 1, d, curve OID, public key. */
    private const SEC1_PREFIX = '30770201010420';
    private const SEC1_MIDDLE = 'a00a06082a8648ce3d030107a144034200';

    /**
     * A new key pair.
     *
     * @return array{public: string, private: string} raw public point (65 bytes) and d (32 bytes)
     */
    public static function generate(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        if ($key === false) {
            throw new RuntimeException('OpenSSL could not generate a P-256 key.');
        }

        $details = openssl_pkey_get_details($key);

        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException('OpenSSL did not return the P-256 key parts.');
        }

        return [
            'public'  => "\x04" . self::pad((string) $details['ec']['x']) . self::pad((string) $details['ec']['y']),
            'private' => self::pad((string) $details['ec']['d']),
        ];
    }

    /** Whether the bytes are an uncompressed P-256 point OpenSSL accepts. */
    public static function isPublicKey(string $raw): bool
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            return false;
        }

        return @openssl_pkey_get_public(self::publicPem($raw)) !== false;
    }

    public static function publicKey(string $raw): OpenSSLAsymmetricKey
    {
        $key = strlen($raw) === 65 && $raw[0] === "\x04" ? @openssl_pkey_get_public(self::publicPem($raw)) : false;

        if ($key === false) {
            throw new RuntimeException('Invalid P-256 public key.');
        }

        return $key;
    }

    /**
     * @param string $private d (32 bytes)
     * @param string $public  the matching uncompressed point (65 bytes)
     */
    public static function privateKey(string $private, string $public): OpenSSLAsymmetricKey
    {
        if (strlen($private) !== 32 || strlen($public) !== 65 || $public[0] !== "\x04") {
            throw new RuntimeException('Invalid P-256 private key.');
        }

        $der = hex2bin(self::SEC1_PREFIX) . $private . hex2bin(self::SEC1_MIDDLE) . $public;
        $key = @openssl_pkey_get_private(self::pem($der, 'EC PRIVATE KEY'));

        if ($key === false) {
            throw new RuntimeException('Invalid P-256 private key.');
        }

        return $key;
    }

    public static function publicPem(string $raw): string
    {
        return self::pem(hex2bin(self::SPKI_PREFIX) . $raw, 'PUBLIC KEY');
    }

    /**
     * ECDH shared secret (the X coordinate, 32 bytes).
     */
    public static function sharedSecret(OpenSSLAsymmetricKey $private, OpenSSLAsymmetricKey $peerPublic): string
    {
        $secret = openssl_pkey_derive($peerPublic, $private, 32);

        if ($secret === false || strlen($secret) !== 32) {
            throw new RuntimeException('ECDH failed.');
        }

        return $secret;
    }

    private static function pad(string $bytes): string
    {
        return str_pad($bytes, 32, "\0", STR_PAD_LEFT);
    }

    private static function pem(string $der, string $label): string
    {
        return "-----BEGIN {$label}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$label}-----\n";
    }
}
