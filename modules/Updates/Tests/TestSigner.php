<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

/**
 * A minisign-compatible signer for tests only (a key pair generated per test). Real releases are
 * signed with `minisign -S` on the owner's computer (docs/updates.md); no private key exists in
 * the repository.
 */
final class TestSigner
{
    public readonly string $keyId;

    private readonly string $secret;

    private readonly string $public;

    public function __construct()
    {
        $pair         = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->public = sodium_crypto_sign_publickey($pair);
        $this->keyId  = random_bytes(8);
    }

    /** The second line of `minisign.pub`. */
    public function publicKey(): string
    {
        return base64_encode('Ed' . $this->keyId . $this->public);
    }

    /**
     * A `.minisig` file for some contents (`ED`: pre-hashed with BLAKE2b-512, minisign's default).
     */
    public function sign(string $contents, bool $prehashed = true, string $trusted = 'timestamp:1763290000	file:release.zip'): string
    {
        $message   = $prehashed ? sodium_crypto_generichash($contents, '', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX) : $contents;
        $signature = sodium_crypto_sign_detached($message, $this->secret);
        $global    = sodium_crypto_sign_detached($signature . $trusted, $this->secret);

        return "untrusted comment: signature from minisign secret key\n"
            . base64_encode(($prehashed ? 'ED' : 'Ed') . $this->keyId . $signature) . "\n"
            . 'trusted comment: ' . $trusted . "\n"
            . base64_encode($global) . "\n";
    }
}
