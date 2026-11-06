<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

/**
 * A file written by the Storage service (a `stored_files` row). `key` is the random identifier of
 * `files/private/{key}`; `path` is relative to the disk and never shown to users of private
 * files; `originalName` is only metadata (download name).
 */
final class StoredFile
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $extension,
        public readonly string $mime,
        public readonly int $size,
        public readonly string $checksum,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?int $uploadedBy,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['file_key'],
            (string) $row['disk'],
            (string) $row['path'],
            (string) $row['original_name'],
            (string) $row['extension'],
            (string) $row['mime'],
            (int) $row['size'],
            (string) $row['checksum'],
            isset($row['width']) ? (int) $row['width'] : null,
            isset($row['height']) ? (int) $row['height'] : null,
            isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            (string) $row['created_at'],
        );
    }

    /**
     * A raster image (SVG is not: it can carry active content).
     */
    public function isImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp'], true);
    }

    /**
     * Shown in the browser (`inline`): raster images and PDF. Everything else is served as an
     * `attachment`.
     */
    public function isInline(): bool
    {
        return $this->isImage() || $this->mime === 'application/pdf';
    }
}
