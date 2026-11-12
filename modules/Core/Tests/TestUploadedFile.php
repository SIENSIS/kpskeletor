<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * An uploaded file for tests: PHP's `is_uploaded_file()` is false for files a test creates, so
 * `isValid()` only checks the upload error. Everything else (MIME detection, re-encoding,
 * moving) is the real code.
 */
final class TestUploadedFile extends UploadedFile
{
    public static function fromContents(string $contents, string $name): self
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kpt');
        file_put_contents($path, $contents);

        return new self($path, $name, null, strlen($contents), UPLOAD_ERR_OK);
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
}
