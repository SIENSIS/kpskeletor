<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use RuntimeException;

/**
 * A storage operation that cannot be done: an invalid or unsafe path, an unknown disk, a
 * public URL asked for a private disk.
 */
final class StorageException extends RuntimeException
{
    public static function invalidPath(string $path): self
    {
        return new self('Invalid storage path: ' . json_encode($path, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public static function invalidSvg(): self
    {
        return new self('The SVG document is not valid or contains a DOCTYPE.');
    }

    public static function unknownDisk(string $disk): self
    {
        return new self("Unknown storage disk \"{$disk}\".");
    }

    public static function notPublic(string $disk): self
    {
        return new self("The disk \"{$disk}\" has no public URL: serve its files through files/private/{key}.");
    }
}
