<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

/**
 * Verifies release archives (docs/updates.md): the SHA-256 published with the release **and**
 * an Ed25519 signature in the minisign format (https://jedisct1.github.io/minisign/), checked
 * with libsodium against the public keys of `Config\Updates`.
 *
 * Public key (`minisign.pub`, second line): base64 of `Ed` + key id (8 bytes) + key (32 bytes).
 * Signature (`<file>.minisig`): an untrusted comment, base64 of algorithm (`Ed`: the file
 * itself; `ED`: its BLAKE2b-512 hash, minisign's default) + key id + signature (64 bytes), a
 * trusted comment and base64 of the global signature (signature + trusted comment). Both
 * signatures must be valid and the key id must be one of the trusted keys.
 */
final class SignatureVerifier
{
    /**
     * @param list<string> $publicKeys minisign public keys (base64 lines, or whole `.pub` files)
     */
    public function __construct(private readonly array $publicKeys)
    {
    }

    /**
     * @throws UpdateException when the file is not signed by a trusted key or the checksum differs
     */
    public function verifyFile(string $file, string $signature, string $checksum): void
    {
        $this->verifyChecksum($file, $checksum);
        $this->verifySignature($file, $signature);
    }

    /**
     * The checksum file holds the hexadecimal SHA-256 first (`sha256sum` output is accepted).
     *
     * @throws UpdateException
     */
    public function verifyChecksum(string $file, string $checksum): void
    {
        if (preg_match('/\A\s*([0-9a-f]{64})\b/i', $checksum, $m) !== 1) {
            throw new UpdateException(lang('Updates.error.checksumFormat'));
        }

        $actual = hash_file('sha256', $file);

        if ($actual === false || ! hash_equals(strtolower($m[1]), $actual)) {
            throw new UpdateException(lang('Updates.error.checksum'));
        }
    }

    /**
     * @throws UpdateException
     */
    public function verifySignature(string $file, string $signature): void
    {
        $keys = $this->keys();

        if ($keys === []) {
            throw new UpdateException(lang('Updates.error.noKey'));
        }

        $parsed = self::parseSignature($signature);

        if ($parsed === null) {
            throw new UpdateException(lang('Updates.error.signatureFormat'));
        }

        $key = $keys[$parsed['keyId']] ?? null;

        if ($key === null) {
            throw new UpdateException(lang('Updates.error.unknownKey', [strtoupper(bin2hex(strrev($parsed['keyId'])))]));
        }

        $message = $parsed['algorithm'] === 'ED' ? self::blake2b($file) : (string) file_get_contents($file);

        if (! sodium_crypto_sign_verify_detached($parsed['signature'], $message, $key)
            || ! sodium_crypto_sign_verify_detached($parsed['global'], $parsed['signature'] . $parsed['trusted'], $key)) {
            throw new UpdateException(lang('Updates.error.signature'));
        }
    }

    /**
     * Trusted keys by key id.
     *
     * @return array<string, string> key id (8 bytes) => Ed25519 public key (32 bytes)
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->publicKeys as $text) {
            $key = self::parsePublicKey($text);

            if ($key !== null) {
                $keys[$key['keyId']] = $key['key'];
            }
        }

        return $keys;
    }

    /**
     * @return array{keyId: string, key: string}|null
     */
    public static function parsePublicKey(string $text): ?array
    {
        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, 'untrusted comment:')) {
                continue;
            }

            $raw = base64_decode($line, true);

            if ($raw !== false && strlen($raw) === 42 && substr($raw, 0, 2) === 'Ed') {
                return ['keyId' => substr($raw, 2, 8), 'key' => substr($raw, 10, 32)];
            }
        }

        return null;
    }

    /**
     * @return array{algorithm: string, keyId: string, signature: string, trusted: string, global: string}|null
     */
    public static function parseSignature(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', trim($text)) ?: []), static fn (string $line): bool => $line !== ''));

        if (count($lines) !== 4 || ! str_starts_with($lines[0], 'untrusted comment:') || ! str_starts_with($lines[2], 'trusted comment: ')) {
            return null;
        }

        $raw    = base64_decode($lines[1], true);
        $global = base64_decode($lines[3], true);

        if ($raw === false || strlen($raw) !== 74 || $global === false || strlen($global) !== 64) {
            return null;
        }

        $algorithm = substr($raw, 0, 2);

        if (! in_array($algorithm, ['Ed', 'ED'], true)) {
            return null;
        }

        return [
            'algorithm' => $algorithm,
            'keyId'     => substr($raw, 2, 8),
            'signature' => substr($raw, 10, 64),
            'trusted'   => substr($lines[2], strlen('trusted comment: ')),
            'global'    => $global,
        ];
    }

    /**
     * BLAKE2b-512 of a file, read in chunks (minisign's pre-hashed mode).
     */
    private static function blake2b(string $file): string
    {
        $state  = sodium_crypto_generichash_init('', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new UpdateException(lang('Updates.error.read'));
        }

        while (! feof($handle)) {
            $chunk = fread($handle, 1 << 20);

            if ($chunk === false) {
                break;
            }

            sodium_crypto_generichash_update($state, $chunk);
        }

        fclose($handle);

        return sodium_crypto_generichash_final($state, SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
    }
}
