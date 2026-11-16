<?php

declare(strict_types=1);

namespace Modules\Core\Archive;

use ZipArchive;

/**
 * Reads ZIP archives that come from outside (backups, module packages) without trusting them
 * (docs/security.md, "Archives"):
 *
 * - **zip-slip:** every entry name is relative, uses `/`, has no `..`, `.` or empty segments, no
 *   NUL or control characters, no drive letter and fits under one of the allowed prefixes;
 *   symbolic links are refused (Unix mode in the external attributes);
 * - **zip bomb:** a maximum number of entries, of total uncompressed size and of compression
 *   ratio per entry, checked on the central directory before anything is extracted, and again
 *   while each entry is copied (the declared size is not trusted);
 * - extraction writes each file itself (never `ZipArchive::extractTo()`), with the given file
 *   permissions, only inside the target folder.
 *
 * ```php
 * $zip = SafeZip::open($path, ['manifest.json', 'database/', 'files/'], maxBytes: 20 * 1024 ** 3);
 * $zip->extract('database/', $folder);
 * ```
 */
final class SafeZip
{
    /** Default limits (callers can lower or raise them). */
    public const MAX_ENTRIES = 200_000;

    public const MAX_RATIO = 1_000;

    /** Entries smaller than this are not checked for their ratio (tiny files compress well). */
    private const RATIO_FLOOR = 1_048_576;

    private const COPY_CHUNK = 1_048_576;

    /** @var array<string, int> entry name => declared size */
    private array $entries = [];

    /** @var array<string, int> entry name => index in the archive */
    private array $indexes = [];

    private int $total = 0;

    /**
     * @param list<string> $prefixes Allowed entry names: an exact name, or a folder ending in `/`
     */
    private function __construct(private readonly ZipArchive $zip, private readonly array $prefixes)
    {
    }

    /**
     * Opens and checks an archive.
     *
     * @param list<string> $prefixes Allowed entry names: exact names, or folders ending in `/`
     *
     * @throws ArchiveRefused
     */
    public static function open(string $path, array $prefixes, int $maxBytes, int $maxEntries = self::MAX_ENTRIES, int $maxRatio = self::MAX_RATIO): self
    {
        $zip = new ZipArchive();

        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ArchiveRefused(lang('Core.archive.invalid'));
        }

        $safe = new self($zip, $prefixes);

        if ($zip->numFiles > $maxEntries) {
            $zip->close();

            throw new ArchiveRefused(lang('Core.archive.tooManyEntries', [$maxEntries]));
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);

            if ($stat === false) {
                $zip->close();

                throw new ArchiveRefused(lang('Core.archive.invalid'));
            }

            $name = (string) $stat['name'];
            $raw  = (string) $zip->getNameIndex($i, ZipArchive::FL_UNCHANGED | ZipArchive::FL_ENC_RAW);

            // libzip converts names without the UTF-8 flag from CP437: check the stored bytes too.
            if (preg_match('/[\x00-\x1F\x7F\\\\]/', $raw) === 1 || ! mb_check_encoding($name, 'UTF-8')) {
                $safe->refuse($name);
            }

            if (str_ends_with($name, '/')) {
                // Folder entries are created on demand; check the name all the same.
                $safe->checkName(rtrim($name, '/') . '/x');

                continue;
            }

            $safe->checkName($name);

            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                $zip->close();

                throw new ArchiveRefused(lang('Core.archive.link', [$name]));
            }

            $size       = (int) $stat['size'];
            $compressed = max(1, (int) $stat['comp_size']);

            if ($size > self::RATIO_FLOOR && intdiv($size, $compressed) > $maxRatio) {
                $zip->close();

                throw new ArchiveRefused(lang('Core.archive.ratio', [$name]));
            }

            if (isset($safe->entries[$name])) {
                $zip->close();

                throw new ArchiveRefused(lang('Core.archive.duplicate', [$name]));
            }

            $safe->entries[$name] = $size;
            $safe->indexes[$name] = $i;
            $safe->total += $size;

            if ($safe->total > $maxBytes) {
                $zip->close();

                throw new ArchiveRefused(lang('Core.archive.tooLarge'));
            }
        }

        return $safe;
    }

    /**
     * @return array<string, int> entry name => size (files only)
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    /**
     * The contents of a (small) entry.
     *
     * @throws ArchiveRefused
     */
    public function read(string $name, int $maxBytes = 16_777_216): string
    {
        $stream = $this->stream($name);
        $data   = '';

        while (! feof($stream)) {
            $chunk = fread($stream, self::COPY_CHUNK);

            if ($chunk === false) {
                break;
            }

            $data .= $chunk;

            if (strlen($data) > min($maxBytes, $this->entries[$name])) {
                fclose($stream);

                throw new ArchiveRefused(lang('Core.archive.sizeMismatch', [$name]));
            }
        }

        fclose($stream);

        return $data;
    }

    /**
     * SHA-256 of an entry, read in chunks (no more than its declared size).
     *
     * @throws ArchiveRefused
     */
    public function hash(string $name): string
    {
        $stream = $this->stream($name);
        $ctx    = hash_init('sha256');
        $read   = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, self::COPY_CHUNK);

            if ($chunk === false) {
                break;
            }

            $read += strlen($chunk);

            if ($read > $this->entries[$name]) {
                fclose($stream);

                throw new ArchiveRefused(lang('Core.archive.sizeMismatch', [$name]));
            }

            hash_update($ctx, $chunk);
        }

        fclose($stream);

        return hash_final($ctx);
    }

    /**
     * Extracts the entries under `$prefix` into `$target` (the prefix removed from their names);
     * returns how many files were written.
     *
     * @param list<string> $skipExtensions Extensions (lower case) never written, e.g. server code
     *
     * @throws ArchiveRefused
     */
    public function extract(string $prefix, string $target, int $filePermission = 0640, int $dirPermission = 0750, array $skipExtensions = []): int
    {
        $target = rtrim($target, '/');
        $count  = 0;

        if (! is_dir($target) && ! mkdir($target, $dirPermission, true) && ! is_dir($target)) {
            throw new ArchiveRefused(lang('Core.archive.cannotWrite'));
        }

        foreach ($this->entries as $name => $size) {
            if (! str_starts_with($name, $prefix)) {
                continue;
            }

            $relative  = substr($name, strlen($prefix));
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if ($relative === '' || ($skipExtensions !== [] && (in_array($extension, $skipExtensions, true) || in_array(strtolower(basename($relative)), $skipExtensions, true)))) {
                continue;
            }

            $destination = $target . '/' . $relative;
            $folder      = dirname($destination);

            if (! is_dir($folder) && ! mkdir($folder, $dirPermission, true) && ! is_dir($folder)) {
                throw new ArchiveRefused(lang('Core.archive.cannotWrite'));
            }

            $this->copyTo($name, $destination, $size);
            @chmod($destination, $filePermission);
            $count++;
        }

        return $count;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Checks one entry name (zip-slip).
     *
     * @throws ArchiveRefused
     */
    private function checkName(string $name): void
    {
        $bad = $name === ''
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || preg_match('/^[A-Za-z]:/', $name) === 1;

        if (! $bad) {
            foreach (explode('/', $name) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    $bad = true;
                    break;
                }
            }
        }

        if (! $bad) {
            $bad = true;

            foreach ($this->prefixes as $prefix) {
                if ($name === $prefix || (str_ends_with($prefix, '/') && str_starts_with($name, $prefix))) {
                    $bad = false;
                    break;
                }
            }
        }

        if ($bad) {
            $this->refuse($name);
        }
    }

    /**
     * @throws ArchiveRefused
     */
    private function refuse(string $name): never
    {
        $this->zip->close();

        throw new ArchiveRefused(lang('Core.archive.badName', [mb_substr((string) preg_replace('/[^\x20-\x7E]/', '?', $name), 0, 200)]));
    }

    /**
     * @return resource
     *
     * @throws ArchiveRefused
     */
    private function stream(string $name)
    {
        if (! isset($this->entries[$name])) {
            throw new ArchiveRefused(lang('Core.archive.missing', [$name]));
        }

        $stream = $this->zip->getStreamIndex($this->indexes[$name]);

        if ($stream === false) {
            throw new ArchiveRefused(lang('Core.archive.invalid'));
        }

        return $stream;
    }

    /**
     * @throws ArchiveRefused
     */
    private function copyTo(string $name, string $destination, int $size): void
    {
        $in  = $this->stream($name);
        $out = fopen($destination, 'xb');

        if ($out === false) {
            fclose($in);

            throw new ArchiveRefused(lang('Core.archive.cannotWrite'));
        }

        $written = 0;

        while (! feof($in)) {
            $chunk = fread($in, self::COPY_CHUNK);

            if ($chunk === false) {
                break;
            }

            $written += strlen($chunk);

            if ($written > $size) {
                fclose($in);
                fclose($out);
                @unlink($destination);

                throw new ArchiveRefused(lang('Core.archive.sizeMismatch', [$name]));
            }

            fwrite($out, $chunk);
        }

        fclose($in);
        fclose($out);
    }
}
