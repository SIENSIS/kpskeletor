<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use GdImage;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StoredFile;
use Throwable;

/**
 * Thumbnails of raster images, generated on upload with GD: at most 320×320 pixels, WebP (JPEG
 * without WebP support), written to the private disk under `modules/files/thumbnails/` (a module
 * folder: never listed) and served by the file manager after the folder's `view` right. A
 * failure only leaves the file without a thumbnail.
 */
final class ThumbnailService
{
    public const SIZE   = 320;
    public const FOLDER = 'modules/files/thumbnails';

    public function __construct(private readonly ?Storage $storage = null)
    {
    }

    /**
     * Makes the thumbnail of a stored file: its path on the private disk, or null.
     */
    public function make(StoredFile $file): ?string
    {
        if (! $file->isImage() || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $source = @imagecreatefromstring($this->storage()->disk($file->disk)->read($file->path));

            if (! $source instanceof GdImage) {
                return null;
            }

            $width  = imagesx($source);
            $height = imagesy($source);
            $scale  = min(1, self::SIZE / max($width, $height, 1));
            $thumb  = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);

            ob_start();
            $webp = function_exists('imagewebp') && imagewebp($thumb, null, 80);

            if (! $webp) {
                imagejpeg($thumb, null, 80);
            }

            $contents = (string) ob_get_clean();
            $path     = self::FOLDER . '/' . $file->key . ($webp ? '.webp' : '.jpg');
            $this->storage()->disk('private')->write($path, $contents);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            $disk = $this->storage()->disk('private');

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable) {
            // a missing thumbnail is not an error
        }
    }

    private function storage(): Storage
    {
        return $this->storage ?? service('storage');
    }
}
