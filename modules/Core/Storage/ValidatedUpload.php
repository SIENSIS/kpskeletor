<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

/**
 * An upload that passed the rules: its extension, the MIME type it is stored and served with,
 * and the bytes to store (re-encoded image or sanitised SVG), or null to store the uploaded file
 * as it is.
 */
final class ValidatedUpload
{
    public function __construct(
        public readonly string $extension,
        public readonly string $mime,
        public readonly ?string $contents,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {
    }

    public function isImage(): bool
    {
        return $this->width !== null;
    }
}
