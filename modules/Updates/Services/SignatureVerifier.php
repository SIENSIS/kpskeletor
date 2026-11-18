<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Modules\Core\Security\Minisign;
use Modules\Core\Security\MinisignRefused;

/**
 * Verifies release archives (docs/updates.md): the SHA-256 published with the release **and**
 * an Ed25519 signature in the minisign format, checked by the Core's `Minisign` (libsodium)
 * against the public keys of `Config\Updates`.
 */
final class SignatureVerifier
{
    private readonly Minisign $minisign;

    /**
     * @param list<string> $publicKeys minisign public keys (base64 lines, or whole `.pub` files)
     */
    public function __construct(array $publicKeys)
    {
        $this->minisign = new Minisign($publicKeys);
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
        try {
            $this->minisign->verify($file, $signature);
        } catch (MinisignRefused $e) {
            throw new UpdateException(match ($e->reason) {
                Minisign::NO_KEY      => lang('Updates.error.noKey'),
                Minisign::FORMAT      => lang('Updates.error.signatureFormat'),
                Minisign::UNKNOWN_KEY => lang('Updates.error.unknownKey', [$e->keyId]),
                Minisign::UNREADABLE  => lang('Updates.error.read'),
                default               => lang('Updates.error.signature'),
            });
        }
    }

    /**
     * Trusted keys by key id.
     *
     * @return array<string, string> key id (8 bytes) => Ed25519 public key (32 bytes)
     */
    public function keys(): array
    {
        return $this->minisign->keys();
    }
}
