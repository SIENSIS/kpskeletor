<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use GdImage;
use Modules\Core\Theme\ThemeManager;
use RuntimeException;
use Throwable;

/**
 * Minimal PWA (setting `Core.pwa`, off by default): the web app manifest, the theme colours for
 * light and dark mode and the PNG icons (192 and 512 px) generated with GD from the public
 * theme's logo. Icons are regenerated whenever the logo (or the primary colour) changes: their
 * file name carries a hash of the source, so a changed logo simply produces new files in
 * `writable/pwa/` (the web server never writes the code or `public/`). An SVG or remote logo is
 * never read (no outbound HTTP): the default icon is drawn instead.
 */
final class Pwa
{
    public const SIZES = [192, 512];

    private const LOGO_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    private const DARK_BACKGROUND = '#212529';

    private readonly string $folder;

    public function __construct(private readonly ThemeManager $theme, ?string $folder = null)
    {
        $this->folder = rtrim($folder ?? WRITEPATH . 'pwa', '/\\');
    }

    public static function enabled(): bool
    {
        try {
            return setting('Core.pwa') === true;
        } catch (Throwable) {
            return false; // settings not installed yet
        }
    }

    /**
     * `theme-color` per colour scheme.
     *
     * @return array{light: string, dark: string}
     */
    public function themeColors(): array
    {
        return ['light' => $this->primaryColor(), 'dark' => self::DARK_BACKGROUND];
    }

    /**
     * The web app manifest (`/manifest.webmanifest`).
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $name  = site_name();
        $start = $this->startArea() === 'backend' ? base_url('backend') : base_url('/');
        $icons = [];

        foreach (self::SIZES as $size) {
            $icons[] = [
                'src'     => base_url('pwa/icon-' . $size . '.png') . '?v=' . $this->hash($size),
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }

        return [
            'name'             => $name,
            'short_name'       => mb_substr($name, 0, 12),
            'lang'             => service('request')->getLocale(),
            'start_url'        => $start,
            'scope'            => base_url('/'),
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => $this->primaryColor(),
            'icons'            => $icons,
        ];
    }

    /**
     * Path of the PNG icon of a size, generated when missing (and stale icons removed).
     */
    public function icon(int $size): string
    {
        if (! in_array($size, self::SIZES, true)) {
            throw new RuntimeException('Unsupported icon size ' . $size);
        }

        $path = $this->folder . '/icon-' . $size . '-' . $this->hash($size) . '.png';

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($this->folder)) {
            mkdir($this->folder, 0755, true);
        }

        foreach (glob($this->folder . '/icon-' . $size . '-*.png') ?: [] as $old) {
            unlink($old);
        }

        $image = $this->draw($size);
        imagepng($image, $path . '.tmp');
        rename($path . '.tmp', $path);

        return $path;
    }

    /**
     * A local raster logo of the public theme (path under the public folder), or null.
     */
    public function logoFile(): ?string
    {
        $logo = (string) $this->theme->options('public')->get('logo');

        if ($logo === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $logo) === 1 || str_contains($logo, '..')) {
            return null;
        }

        $file = realpath(FCPATH . ltrim($logo, '/'));
        $root = realpath(FCPATH);

        if ($file === false || $root === false || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }

        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::LOGO_TYPES, true) ? $file : null;
    }

    public function hash(int $size): string
    {
        $logo   = $this->logoFile();
        $source = $logo === null ? 'default' : $logo . '|' . filemtime($logo) . '|' . filesize($logo);

        return substr(sha1($source . '|' . $this->primaryColor() . '|' . $size . '|v1'), 0, 12);
    }

    private function startArea(): string
    {
        try {
            return setting('Core.pwaStart') === 'backend' ? 'backend' : 'public';
        } catch (Throwable) {
            return 'public';
        }
    }

    private function primaryColor(): string
    {
        $color = $this->theme->options('public')->get('primaryColor');

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : '#0d6efd';
    }

    private function draw(int $size): GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        $logo  = $this->logoFile();
        $logo  = $logo === null ? false : @imagecreatefromstring((string) file_get_contents($logo));

        if ($logo instanceof GdImage) {
            // The logo centred on white, 10% padding, aspect ratio kept.
            imagefill($image, 0, 0, $this->color($image, '#ffffff'));
            $width  = imagesx($logo);
            $height = imagesy($logo);
            $scale  = min(($size * 0.8) / $width, ($size * 0.8) / $height);
            $w      = max(1, (int) round($width * $scale));
            $h      = max(1, (int) round($height * $scale));
            imagecopyresampled($image, $logo, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, $width, $height);

            return $image;
        }

        // Default icon: the brand's 3×3 grid in white on the primary colour.
        imagefill($image, 0, 0, $this->color($image, $this->primaryColor()));
        $white = $this->color($image, '#ffffff');
        $cell  = $size * 0.16;
        $gap   = $size * 0.07;
        $start = ($size - (3 * $cell + 2 * $gap)) / 2;

        for ($row = 0; $row < 3; $row++) {
            for ($col = 0; $col < 3; $col++) {
                $x = (int) round($start + $col * ($cell + $gap));
                $y = (int) round($start + $row * ($cell + $gap));
                imagefilledrectangle($image, $x, $y, $x + (int) round($cell), $y + (int) round($cell), $white);
            }
        }

        return $image;
    }

    private function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return (int) imagecolorallocate($image, (int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)));
    }
}
