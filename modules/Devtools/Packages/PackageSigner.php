<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

use Modules\Core\Security\Minisign;
use Modules\Core\Security\MinisignRefused;

/**
 * Signs a package with a minisign secret key (D-43-01): minisign's pre-hashed mode (`ED`:
 * Ed25519 over the BLAKE2b-512 of the file), trusted comment `<Name> <version>`; writes
 * `<file>.minisig` and then verifies it with the Core's `Minisign` against the public key derived
 * from the secret key, so a signature is offered only once the installer would accept it.
 *
 * The secret key and its password are only parameters (`#[\SensitiveParameter]`: never in a
 * backtrace) and are wiped after use; the only files written are the signature and the public
 * description `<file>.json` (key id, public key, SHA-256), which hold nothing secret.
 */
final class PackageSigner
{
    /**
     * @param-out string $keyText
     * @param-out string $password
     *
     * @throws SigningFailed
     */
    public function sign(string $zip, string $trustedComment, #[\SensitiveParameter] string &$keyText, #[\SensitiveParameter] string &$password): SignResult
    {
        if (! is_file($zip) || ! is_readable($zip)) {
            self::wipe($keyText, $password);

            throw new SigningFailed('package');
        }

        try {
            $key = MinisignSecretKey::parse($keyText, $password);
        } finally {
            self::wipe($keyText, $password);
        }

        try {
            $signature = self::signature($zip, $key, $trustedComment);
            $publicKey = $key->publicKey();
            $keyId     = $key->keyIdHex();
        } finally {
            $key->wipe();
        }

        try {
            (new Minisign([$publicKey]))->verify($zip, $signature);
        } catch (MinisignRefused) {
            throw new SigningFailed('verify');
        }

        if (file_put_contents($zip . '.minisig', $signature, LOCK_EX) === false) {
            throw new SigningFailed('failed');
        }

        $result = new SignResult(basename($zip), $keyId, $publicKey, (string) hash_file('sha256', $zip), $trustedComment);
        PackageStore::writeInfo($zip, $result->toArray());

        return $result;
    }

    /**
     * The `.minisig` text of a file (minisign 0.11's format, byte for byte).
     */
    public static function signature(string $file, MinisignSecretKey $key, string $trustedComment): string
    {
        $trustedComment = str_replace(["\r", "\n"], ' ', $trustedComment);
        $hash           = self::blake2b($file);
        $signature      = $key->sign($hash);
        $global         = $key->sign($signature . $trustedComment);

        return "untrusted comment: signature from minisign secret key\n"
            . base64_encode('ED' . $key->keyId . $signature) . "\n"
            . 'trusted comment: ' . $trustedComment . "\n"
            . base64_encode($global) . "\n";
    }

    private static function blake2b(string $file): string
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new SigningFailed('package');
        }

        $state = sodium_crypto_generichash_init('', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);

        while (! feof($handle)) {
            $chunk = fread($handle, 1 << 20);

            if ($chunk === false || $chunk === '') {
                break;
            }

            sodium_crypto_generichash_update($state, $chunk);
        }

        fclose($handle);

        return sodium_crypto_generichash_final($state, SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
    }

    /**
     * @param-out string $keyText
     * @param-out string $password
     */
    private static function wipe(?string &$keyText, ?string &$password): void
    {
        if (is_string($keyText) && $keyText !== '') {
            sodium_memzero($keyText); // @phpstan-ignore paramOut.type (set to the empty string below)
        }

        if (is_string($password) && $password !== '') {
            sodium_memzero($password); // @phpstan-ignore paramOut.type (set to the empty string below)
        }

        $keyText  = '';
        $password = '';
    }
}
