<?php

declare(strict_types=1);

namespace Modules\Core\Security;

/**
 * Minisign signatures (https://jedisct1.github.io/minisign/), checked with libsodium: the one
 * verifier of release archives (Updates, docs/updates.md) and module/theme packages
 * (`Modules\Core\Packages\SignatureCheck`, docs/installing-modules.md).
 *
 * Public key (`minisign.pub`, second line): base64 of `Ed` + key id (8 bytes) + key (32 bytes).
 * Signature (`<file>.minisig`): an untrusted comment, base64 of algorithm (`Ed`: the file
 * itself; `ED`: its BLAKE2b-512 hash, minisign's default) + key id + signature (64 bytes), a
 * trusted comment and base64 of the global signature (signature + trusted comment). Both
 * signatures must be valid and the key id must be one of the trusted keys.
 *
 * Callers translate the refusal reason (`MinisignRefused::$reason`) into their own messages.
 */
final class Minisign
{
    public const NO_KEY      = 'noKey';
    public const FORMAT      = 'format';
    public const UNKNOWN_KEY = 'unknownKey';
    public const SIGNATURE   = 'signature';
    public const UNREADABLE  = 'unreadable';

    /**
     * @param list<string> $publicKeys minisign public keys (base64 lines, or whole `.pub` files)
     */
    public function __construct(private readonly array $publicKeys)
    {
    }

    /**
     * @throws MinisignRefused when the file is not signed by a trusted key
     */
    public function verify(string $file, string $signature): void
    {
        $keys = $this->keys();

        if ($keys === []) {
            throw new MinisignRefused(self::NO_KEY);
        }

        $parsed = self::parseSignature($signature);

        if ($parsed === null) {
            throw new MinisignRefused(self::FORMAT);
        }

        $key = $keys[$parsed['keyId']] ?? null;

        if ($key === null) {
            throw new MinisignRefused(self::UNKNOWN_KEY, strtoupper(bin2hex(strrev($parsed['keyId']))));
        }

        $message = $parsed['algorithm'] === 'ED' ? self::blake2b($file) : (string) @file_get_contents($file);

        if (! sodium_crypto_sign_verify_detached($parsed['signature'], $message, $key)
            || ! sodium_crypto_sign_verify_detached($parsed['global'], $parsed['signature'] . $parsed['trusted'], $key)) {
            throw new MinisignRefused(self::SIGNATURE);
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
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new MinisignRefused(self::UNREADABLE);
        }

        $state = sodium_crypto_generichash_init('', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);

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
