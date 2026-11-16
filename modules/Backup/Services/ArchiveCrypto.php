<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Modules\Backup\Config\Backup as BackupConfig;
use SodiumException;

/**
 * Streaming, authenticated encryption of backup files (D-00-46): libsodium
 * `crypto_secretstream_xchacha20poly1305` with a key derived from the password by Argon2id.
 *
 * File format (version 1), all integers big-endian:
 *
 * | Bytes | Content                                                    |
 * |-------|------------------------------------------------------------|
 * | 8     | magic `KPBACKUP`                                           |
 * | 1     | format version (1)                                         |
 * | 1     | key derivation (1 = Argon2id 1.3)                          |
 * | 4     | Argon2id opslimit                                          |
 * | 8     | Argon2id memlimit (bytes)                                  |
 * | 16    | random salt                                                |
 * | 24    | secretstream header                                        |
 * | …     | chunks: 4-byte length + ciphertext of up to 1 MiB of plain |
 *
 * The 62-byte header is the additional data of every chunk, so a changed header fails like a
 * changed chunk; the last chunk carries the FINAL tag, so a truncated file or bytes after it are
 * refused. A wrong password fails on the first chunk. Nothing decrypted is used before the whole
 * file has been authenticated (callers decrypt to a temporary file first).
 */
final class ArchiveCrypto
{
    public const MAGIC = 'KPBACKUP';

    public const VERSION = 1;

    public const KDF_ARGON2ID = 1;

    public const HEADER_BYTES = 62;

    /** Plain bytes per chunk. */
    public const CHUNK = 1_048_576;

    private readonly BackupConfig $config;

    public function __construct(?BackupConfig $config = null)
    {
        $this->config = $config ?? config(BackupConfig::class);
    }

    /**
     * Encrypts `$plain` into `$target` (created with the configured permission).
     *
     * @throws BackupException
     */
    public function encryptFile(string $plain, string $target, #[\SensitiveParameter] string $password): void
    {
        $in = @fopen($plain, 'rb');

        if ($in === false) {
            throw new BackupException(lang('Backup.error.write'));
        }

        $out = @fopen($target, 'xb');

        if ($out === false) {
            fclose($in);

            throw new BackupException(lang('Backup.error.write'));
        }

        @chmod($target, $this->config->filePermission);

        $salt  = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $ops   = $this->config->opsLimit;
        $mem   = $this->config->memLimit;
        $key   = $this->deriveKey($password, $salt, $ops, $mem);

        try {
            [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            $header = self::MAGIC . pack('CCNJ', self::VERSION, self::KDF_ARGON2ID, $ops, $mem) . $salt . $streamHeader;
            fwrite($out, $header);

            $chunk = (string) fread($in, self::CHUNK);

            while (true) {
                $next  = feof($in) ? '' : (string) fread($in, self::CHUNK);
                $last  = $next === '' && feof($in);
                $tag   = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $sealed = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, $header, $tag);

                if (fwrite($out, pack('N', strlen($sealed)) . $sealed) === false) {
                    throw new BackupException(lang('Backup.error.write'));
                }

                if ($last) {
                    break;
                }

                $chunk = $next;
            }
        } catch (SodiumException) {
            throw new BackupException(lang('Backup.error.write'));
        } finally {
            sodium_memzero($key);
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Decrypts and authenticates `$source` into `$target`. On any failure the target is deleted.
     *
     * @throws BackupException wrong password, modified, truncated or unknown file
     */
    public function decryptFile(string $source, string $target, #[\SensitiveParameter] string $password): void
    {
        $in = @fopen($source, 'rb');

        if ($in === false) {
            throw new BackupException(lang('Backup.error.notFound'));
        }

        $header = (string) fread($in, self::HEADER_BYTES);
        $info   = $this->parseHeader($header);
        $out    = @fopen($target, 'xb');

        if ($out === false) {
            fclose($in);

            throw new BackupException(lang('Backup.error.write'));
        }

        @chmod($target, 0600);
        $key = $this->deriveKey($password, $info['salt'], $info['ops'], $info['mem']);
        $ok  = false;

        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($info['stream'], $key);
            $first = true;

            while (true) {
                $length = fread($in, 4);

                if ($length === false || strlen($length) !== 4) {
                    throw new BackupException(lang('Backup.error.tampered'));
                }

                $size = (int) unpack('N', $length)[1];

                if ($size < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $size > self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new BackupException(lang('Backup.error.tampered'));
                }

                $sealed = $this->readExactly($in, $size);
                $result = $sealed === null ? false : sodium_crypto_secretstream_xchacha20poly1305_pull($state, $sealed, $header);

                if ($result === false) {
                    throw new BackupException(lang($first ? 'Backup.error.password' : 'Backup.error.tampered'));
                }

                [$plain, $tag] = $result;
                $first         = false;
                fwrite($out, $plain);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if (fread($in, 1) !== '' || ! feof($in)) {
                        throw new BackupException(lang('Backup.error.tampered'));
                    }

                    break;
                }
            }

            $ok = true;
        } catch (SodiumException) {
            throw new BackupException(lang('Backup.error.tampered'));
        } finally {
            sodium_memzero($key);
            fclose($in);
            fclose($out);

            if (! $ok) {
                @unlink($target);
            }
        }
    }

    /**
     * Whether a password opens a backup: derives the key and authenticates the first chunk only
     * (a quick check before a long job; the whole file is authenticated by decryptFile()).
     */
    public function checkPassword(string $source, #[\SensitiveParameter] string $password): bool
    {
        $in = @fopen($source, 'rb');

        if ($in === false) {
            return false;
        }

        try {
            $header = (string) fread($in, self::HEADER_BYTES);
            $info   = $this->parseHeader($header);
            $length = fread($in, 4);

            if ($length === false || strlen($length) !== 4) {
                return false;
            }

            $size = (int) unpack('N', $length)[1];

            if ($size < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $size > self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                return false;
            }

            $sealed = $this->readExactly($in, $size);
            $key    = $this->deriveKey($password, $info['salt'], $info['ops'], $info['mem']);

            try {
                $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($info['stream'], $key);

                return $sealed !== null && sodium_crypto_secretstream_xchacha20poly1305_pull($state, $sealed, $header) !== false;
            } finally {
                sodium_memzero($key);
            }
        } catch (BackupException|SodiumException) {
            return false;
        } finally {
            fclose($in);
        }
    }

    /**
     * Whether a file starts with a backup header this version can read (no password needed).
     */
    public function isBackup(string $path): bool
    {
        $in = @fopen($path, 'rb');

        if ($in === false) {
            return false;
        }

        $header = (string) fread($in, self::HEADER_BYTES);
        fclose($in);

        try {
            $this->parseHeader($header);

            return true;
        } catch (BackupException) {
            return false;
        }
    }

    /**
     * @return array{version: int, ops: int, mem: int, salt: string, stream: string}
     *
     * @throws BackupException
     */
    public function parseHeader(string $header): array
    {
        if (strlen($header) !== self::HEADER_BYTES || ! str_starts_with($header, self::MAGIC)) {
            throw new BackupException(lang('Backup.error.notBackup'));
        }

        $fields = unpack('Cversion/Ckdf/Nops/Jmem', substr($header, 8, 14));

        if ($fields === false || (int) $fields['version'] !== self::VERSION || (int) $fields['kdf'] !== self::KDF_ARGON2ID) {
            throw new BackupException(lang('Backup.error.format'));
        }

        $ops = (int) $fields['ops'];
        $mem = (int) $fields['mem'];

        if ($ops < SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE || $ops > $this->config->maxOpsLimit
            || $mem < 8192 || $mem > $this->config->maxMemLimit) {
            throw new BackupException(lang('Backup.error.format'));
        }

        return [
            'version' => (int) $fields['version'],
            'ops'     => $ops,
            'mem'     => $mem,
            'salt'    => substr($header, 22, SODIUM_CRYPTO_PWHASH_SALTBYTES),
            'stream'  => substr($header, 38, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),
        ];
    }

    private function deriveKey(#[\SensitiveParameter] string $password, string $salt, int $ops, int $mem): string
    {
        try {
            return sodium_crypto_pwhash(
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
                $password,
                $salt,
                $ops,
                $mem,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
            );
        } catch (SodiumException) {
            throw new BackupException(lang('Backup.error.memory'));
        }
    }

    /**
     * @param resource $in
     */
    private function readExactly($in, int $size): ?string
    {
        $data = '';

        while (strlen($data) < $size && ! feof($in)) {
            $chunk = fread($in, $size - strlen($data));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $data .= $chunk;
        }

        return strlen($data) === $size ? $data : null;
    }
}
