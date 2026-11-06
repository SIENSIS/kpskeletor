<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

/**
 * Normalises a path inside a disk and refuses everything that could leave the disk root or reach
 * a file the web server treats specially: `..` and `.` segments, absolute paths, backslashes,
 * null bytes and control characters, and hidden segments (`.htaccess`, `.env`, `.user.ini`).
 * Repeated and trailing slashes are removed. Flysystem checks traversal too; this check runs
 * first so the rule does not depend on the adapter.
 */
final class PathNormalizer
{
    public const MAX_LENGTH = 500;

    /**
     * @param bool $allowRoot accept the empty path (the disk root, for listings)
     *
     * @throws StorageException
     */
    public static function normalize(string $path, bool $allowRoot = false): string
    {
        if (strlen($path) > self::MAX_LENGTH
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $path) === 1
            || ! mb_check_encoding($path, 'UTF-8')
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || str_contains($path, '://')
        ) {
            throw StorageException::invalidPath($path);
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (str_starts_with($segment, '.') || trim($segment) !== $segment) {
                throw StorageException::invalidPath($path);
            }

            $segments[] = $segment;
        }

        if ($segments === [] && ! $allowRoot) {
            throw StorageException::invalidPath($path);
        }

        return implode('/', $segments);
    }

    /**
     * Joins a folder and a file name, both normalised.
     */
    public static function join(string $folder, string $name): string
    {
        $folder = self::normalize($folder, true);
        $name   = self::normalize($name);

        return $folder === '' ? $name : $folder . '/' . $name;
    }
}
