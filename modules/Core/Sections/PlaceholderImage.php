<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * Placeholder images drawn for the skeleton with GD (PNG; never third-party pictures): demo data
 * and page models (D-47-02) use them until an editor chooses real images.
 *
 * - `gradient`: a vertical gradient with soft light circles (banners, cards, tiles);
 * - `figure`: a simple person on a transparent background (the hero's cut-out image);
 * - `logo`: a neutral mark (a rounded square and two bars) on a transparent background.
 */
final class PlaceholderImage
{
    public const KINDS = ['gradient', 'figure', 'logo'];

    /**
     * @param array{0: int, 1: int, 2: int} $from
     * @param array{0: int, 1: int, 2: int} $to
     */
    public static function png(string $seed, int $width, int $height, array $from, array $to, string $kind = 'gradient'): string
    {
        $width  = max(16, min(2400, $width));
        $height = max(16, min(2400, $height));
        $image  = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, true);

        if ($kind === 'figure') {
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
            $skin  = (int) imagecolorallocate($image, 246, 206, 170);
            $hair  = (int) imagecolorallocate($image, 92, 60, 40);
            $shirt = (int) imagecolorallocate($image, ...self::mix($from, $to, 0.5, [70, 130, 220]));
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.88), (int) ($width * 0.8), (int) ($height * 0.6), $shirt);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.3), (int) ($width * 0.38), (int) ($width * 0.44), $hair);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.33), (int) ($width * 0.32), (int) ($width * 0.38), $skin);
            imagefilledrectangle($image, 0, (int) ($height * 0.98), $width, $height, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        } elseif ($kind === 'logo') {
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
            $main  = (int) imagecolorallocate($image, ...$from);
            $other = (int) imagecolorallocate($image, ...$to);
            $size  = (int) ($height * 0.62);
            $top   = (int) (($height - $size) / 2);
            $left  = (int) ($width * 0.08);
            imagefilledellipse($image, $left + (int) ($size / 2), $top + (int) ($size / 2), $size, $size, $main);
            imagefilledellipse($image, $left + (int) ($size / 2), $top + (int) ($size / 2), (int) ($size * 0.45), (int) ($size * 0.45), (int) imagecolorallocatealpha($image, 255, 255, 255, 20));
            $x = $left + $size + (int) ($width * 0.06);
            imagefilledrectangle($image, $x, $top + (int) ($size * 0.18), (int) ($width * 0.9), $top + (int) ($size * 0.42), $main);
            imagefilledrectangle($image, $x, $top + (int) ($size * 0.58), (int) ($width * 0.75), $top + (int) ($size * 0.78), $other);
        } else {
            for ($y = 0; $y < $height; $y++) {
                $t = $y / max(1, $height - 1);
                imageline($image, 0, $y, $width, $y, (int) imagecolorallocate($image, ...self::mix($from, $to, $t)));
            }

            $hash = crc32($seed);

            for ($i = 0; $i < 7; $i++) {
                $size = (int) (min($width, $height) * (0.18 + (($hash >> $i) & 7) / 20));
                $x    = (int) ($width * ((($hash >> ($i * 3)) & 15) / 15));
                $y    = (int) ($height * ((($hash >> ($i * 2 + 1)) & 15) / 15));
                imagefilledellipse($image, $x, $y, $size, $size, (int) imagecolorallocatealpha($image, 255, 255, 255, 96 + ($i * 4)));
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @param array{0: int, 1: int, 2: int}      $from
     * @param array{0: int, 1: int, 2: int}      $to
     * @param array{0: int, 1: int, 2: int}|null $whenBlack used when both colours are black
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function mix(array $from, array $to, float $t, ?array $whenBlack = null): array
    {
        if ($whenBlack !== null && $from === [0, 0, 0] && $to === [0, 0, 0]) {
            return $whenBlack;
        }

        return [
            max(0, min(255, (int) ($from[0] + ($to[0] - $from[0]) * $t))),
            max(0, min(255, (int) ($from[1] + ($to[1] - $from[1]) * $t))),
            max(0, min(255, (int) ($from[2] + ($to[2] - $from[2]) * $t))),
        ];
    }
}
