<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * A minisign secret key (`minisign.key`), decrypted in memory (D-43-01). The file is an
 * untrusted comment and the base64 of:
 *
 *     sig_alg "Ed" (2) | kdf_alg "Sc" or "\0\0" (2) | chk_alg "B2" (2) | kdf_salt (32)
 *     | kdf_opslimit (8, LE) | kdf_memlimit (8, LE) | key id (8) | secret key (64) | checksum (32)
 *
 * With `Sc` the last 104 bytes are XORed with scrypt(password, salt, opslimit, memlimit)
 * (`crypto_pwhash_scryptsalsa208sha256`); the checksum is BLAKE2b-256 of
 * sig_alg + key id + secret key, so a wrong password is detected. Unencrypted keys
 * (`minisign -G -W`) have `kdf_alg = "\0\0"`.
 *
 * The key is never written anywhere: callers call wipe() when done (`sodium_memzero`).
 */
final class MinisignSecretKey
{
    /** Largest KDF limits accepted (minisign's defaults are 2^25 and 1 GiB). */
    private const MAX_OPSLIMIT = 4_294_967_296;

    private const MAX_MEMLIMIT = 2_147_483_648;

    private const LENGTH = 158;

    private ?string $secret;

    private function __construct(public readonly string $keyId, string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @throws SigningFailed with a reason code (Devtools.packages.sign.errors.<reason>)
     */
    public static function parse(#[\SensitiveParameter] string $text, #[\SensitiveParameter] string $password): self
    {
        $raw = self::decode($text);

        try {
            if (substr($raw, 0, 2) !== 'Ed' || substr($raw, 4, 2) !== 'B2') {
                throw new SigningFailed('format');
            }

            $kdf  = substr($raw, 2, 2);
            $data = substr($raw, 54, 104);

            if ($kdf === 'Sc') {
                if ($password === '') {
                    throw new SigningFailed('passwordRequired');
                }

                $ops = self::uint64(substr($raw, 38, 8));
                $mem = self::uint64(substr($raw, 46, 8));

                if ($ops < SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE || $ops > self::MAX_OPSLIMIT
                    || $mem < SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE || $mem > self::MAX_MEMLIMIT) {
                    throw new SigningFailed('format');
                }

                $stream = sodium_crypto_pwhash_scryptsalsa208sha256(104, $password, substr($raw, 6, 32), $ops, $mem);
                $plain  = $data ^ $stream;
                sodium_memzero($stream);
                sodium_memzero($data);
                $data = $plain;
            } elseif ($kdf !== "\0\0") {
                throw new SigningFailed('format');
            }

            $keyId    = substr($data, 0, 8);
            $secret   = substr($data, 8, 64);
            $checksum = substr($data, 72, 32);
            sodium_memzero($data);

            $state = sodium_crypto_generichash_init('', 32);
            sodium_crypto_generichash_update($state, 'Ed');
            sodium_crypto_generichash_update($state, $keyId);
            sodium_crypto_generichash_update($state, $secret);
            $expected = sodium_crypto_generichash_final($state, 32);

            // minisign 0.11 leaves the checksum of an unencrypted key zeroed.
            $unchecked = $kdf === "\0\0" && $checksum === str_repeat("\0", 32);

            if (! $unchecked && ! hash_equals($expected, $checksum)) {
                sodium_memzero($secret);

                throw new SigningFailed($kdf === 'Sc' ? 'password' : 'format');
            }

            // The secret key is seed + public key: both halves must belong together.
            $pair = sodium_crypto_sign_seed_keypair(substr($secret, 0, 32));
            $same = hash_equals(sodium_crypto_sign_secretkey($pair), $secret);
            sodium_memzero($pair);

            if (! $same) {
                sodium_memzero($secret);

                throw new SigningFailed('format');
            }

            return new self($keyId, $secret);
        } finally {
            sodium_memzero($raw);
        }
    }

    /**
     * Ed25519 detached signature of a message.
     */
    public function sign(string $message): string
    {
        if ($this->secret === null || $this->secret === '') {
            throw new SigningFailed('failed');
        }

        return sodium_crypto_sign_detached($message, $this->secret);
    }

    /** The key id as minisign prints it (hex of the little-endian number). */
    public function keyIdHex(): string
    {
        return strtoupper(bin2hex(strrev($this->keyId)));
    }

    /** The public key line (`RW…`, second line of `minisign.pub`) for `packages.publicKeys`. */
    public function publicKey(): string
    {
        if ($this->secret === null || $this->secret === '') {
            throw new SigningFailed('failed');
        }

        return base64_encode('Ed' . $this->keyId . sodium_crypto_sign_publickey_from_secretkey($this->secret));
    }

    /** The whole `minisign.pub` file. */
    public function publicKeyFile(): string
    {
        return 'untrusted comment: minisign public key ' . $this->keyIdHex() . "\n" . $this->publicKey() . "\n";
    }

    public function wipe(): void
    {
        if ($this->secret !== null && $this->secret !== '') {
            sodium_memzero($this->secret);
        }

        $this->secret = '';
    }

    public function __destruct()
    {
        $this->wipe();
    }

    /**
     * The key's bytes: the base64 line of the file (comment lines ignored).
     */
    private static function decode(#[\SensitiveParameter] string $text): string
    {
        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, 'untrusted comment:')) {
                continue;
            }

            $raw = base64_decode($line, true);

            if ($raw !== false && strlen($raw) === self::LENGTH) {
                return $raw;
            }

            break;
        }

        throw new SigningFailed('format');
    }

    private static function uint64(string $bytes): int
    {
        $value = unpack('P', $bytes);

        return is_array($value) ? (int) $value[1] : 0;
    }
}
