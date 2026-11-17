<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * Verifies the minisign signature (`<package>.zip.minisig`) of a module or theme package against
 * the trusted publisher keys of `Config\Packages::$publicKeys` (docs/installing-modules.md).
 *
 * Same format as release archives (docs/updates.md): an Ed25519 signature of the file (`Ed`) or
 * of its BLAKE2b-512 hash (`ED`, minisign's default) plus the global signature of the trusted
 * comment; the key id must be one of the trusted keys. Checked with libsodium.
 */
final class SignatureCheck
{
    /**
     * @param list<string> $publicKeys minisign public keys (base64 lines, or whole `.pub` files)
     */
    public function __construct(private readonly array $publicKeys)
    {
    }

    /**
     * @throws PackageRefused when the file is not signed by a trusted key
     */
    public function verify(string $file, string $signature): void
    {
        $keys = $this->keys();

        if ($keys === []) {
            throw new PackageRefused(lang('Core.packages.noPublisherKey'));
        }

        $parsed = self::parseSignature($signature);

        if ($parsed === null) {
            throw new PackageRefused(lang('Core.packages.signatureFormat'));
        }

        $key = $keys[$parsed['keyId']] ?? null;

        if ($key === null) {
            throw new PackageRefused(lang('Core.packages.unknownKey', [strtoupper(bin2hex(strrev($parsed['keyId'])))]));
        }

        $message = $parsed['algorithm'] === 'ED' ? self::blake2b($file) : (string) file_get_contents($file);

        if (! sodium_crypto_sign_verify_detached($parsed['signature'], $message, $key)
            || ! sodium_crypto_sign_verify_detached($parsed['global'], $parsed['signature'] . $parsed['trusted'], $key)) {
            throw new PackageRefused(lang('Core.packages.badSignature'));
        }
    }

    /**
     * @return array<string, string> key id (8 bytes) => Ed25519 public key (32 bytes)
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->publicKeys as $text) {
            foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, 'untrusted comment:')) {
                    continue;
                }

                $raw = base64_decode($line, true);

                if ($raw !== false && strlen($raw) === 42 && substr($raw, 0, 2) === 'Ed') {
                    $keys[substr($raw, 2, 8)] = substr($raw, 10, 32);
                }
            }
        }

        return $keys;
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

    private static function blake2b(string $file): string
    {
        $state  = sodium_crypto_generichash_init('', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new PackageRefused(lang('Core.packages.unreadable'));
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
