<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use GdImage;

/**
 * Decodes an image with GD and encodes it again in the same format. The new file has only the
 * pixels: EXIF/XMP/IPTC metadata (GPS position, camera, comments) and anything appended or hidden
 * in the original are dropped. The EXIF orientation of JPEG photos is applied first, so they keep
 * looking right without the metadata. Transparency is kept (PNG, WebP, GIF); animated GIFs keep
 * only their first frame.
 */
final class ImageReencoder
{
    public function __construct(private readonly int $quality = 85)
    {
    }

    /**
     * IMAGETYPE_* constant of an image extension, or null.
     */
    public static function typeOf(string $extension): ?int
    {
        return match ($extension) {
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            'png'  => IMAGETYPE_PNG,
            'gif'  => IMAGETYPE_GIF,
            'webp' => IMAGETYPE_WEBP,
            default => null,
        };
    }

    /**
     * @return array{contents: string, width: int, height: int}
     *
     * @throws UploadRefused when the image cannot be decoded or encoded
     */
    public function reencode(string $path, string $extension): array
    {
        $data  = (string) file_get_contents($path);
        $image = $data === '' ? false : @imagecreatefromstring($data);

        if (! $image instanceof GdImage) {
            throw new UploadRefused('image');
        }

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $image = $this->orient($image, $path);
        }

        ob_start();

        try {
            $ok = match ($extension) {
                'jpg', 'jpeg' => imagejpeg($image, null, $this->quality),
                'png'  => $this->png($image),
                'gif'  => imagegif($image),
                'webp' => $this->webp($image),
                default => false,
            };
        } finally {
            $contents = (string) ob_get_clean();
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        if (! $ok || $contents === '') {
            throw new UploadRefused('image');
        }

        return ['contents' => $contents, 'width' => $width, 'height' => $height];
    }

    private function png(GdImage $image): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, null, 6);
    }

    private function webp(GdImage $image): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagewebp($image, null, $this->quality);
    }

    /**
     * Applies the EXIF orientation of a JPEG photo (the tag is lost by the re-encoding).
     */
    private function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif        = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
