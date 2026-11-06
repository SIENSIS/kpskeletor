<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use Config\Mimes;
use Config\Storage as StorageConfig;
use finfo;

/**
 * Checks an uploaded file against the global rules (`Core.uploadTypes`, `Core.uploadMaxSize`,
 * `Core.uploadAllowSvg`, Config\Storage) and the field's UploadRules:
 *
 * - the extension (of the client's name, lower case) must be allowed by both lists and never be
 *   one of Config\Storage::$forbiddenExtensions; SVG only with `Core.uploadAllowSvg`;
 * - the MIME type detected from the contents (finfo, never the client's) must belong to the
 *   extension; HTML, XML and SVG contents under another extension are refused, and so is PHP
 *   code (`<?php`) at the start of a file;
 * - the size must be within both limits and not empty;
 * - images must decode, match their extension and stay under Config\Storage::$maxImagePixels;
 *   they are re-encoded unless the field turns it off;
 * - SVG files are sanitised (SvgSanitizer).
 */
final class UploadValidator
{
    /** Bytes of the start of the file scanned for code. */
    private const HEAD_BYTES = 65536;

    /** Contents types never accepted under another extension. */
    private const ACTIVE_MIMES = ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml', 'text/x-php', 'application/x-php', 'application/x-httpd-php'];

    /**
     * @param list<string> $globalTypes allowed extensions (setting `Core.uploadTypes`)
     * @param int          $globalMax   bytes (setting `Core.uploadMaxSize`, in MB)
     */
    public function __construct(
        private readonly StorageConfig $config,
        private readonly array $globalTypes,
        private readonly int $globalMax,
        private readonly bool $allowSvg = false,
    ) {
    }

    public static function fromSettings(?StorageConfig $config = null): self
    {
        $megabytes = (int) setting('Core.uploadMaxSize');

        return new self(
            $config ?? config(StorageConfig::class),
            UploadRules::parseTypes((string) setting('Core.uploadTypes')),
            max(1, $megabytes) * 1024 * 1024,
            (bool) setting('Core.uploadAllowSvg'),
        );
    }

    /**
     * Extensions a field may accept: its own list (or the global one) within the global list,
     * without the forbidden ones and without SVG unless allowed.
     *
     * @return list<string>
     */
    public function allowedTypes(UploadRules $rules): array
    {
        $types = $rules->types === [] ? $this->globalTypes : array_values(array_intersect($rules->types, $this->globalTypes));

        return array_values(array_filter(
            $types,
            fn (string $type): bool => ! in_array($type, $this->config->forbiddenExtensions, true) && ($type !== 'svg' || $this->allowSvg),
        ));
    }

    public function maxSize(UploadRules $rules): int
    {
        return $rules->maxSize === null ? $this->globalMax : min($rules->maxSize, $this->globalMax);
    }

    /**
     * @param string $path       the uploaded (temporary) file
     * @param string $clientName the name the browser sent (only its extension is used)
     *
     * @throws UploadRefused
     */
    public function validate(string $path, string $clientName, UploadRules $rules): ValidatedUpload
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new UploadRefused('invalid');
        }

        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if ($extension === '' || in_array($extension, $this->config->forbiddenExtensions, true)) {
            throw new UploadRefused('forbidden', [$extension === '' ? '—' : $extension]);
        }

        if ($extension === 'svg' && ! $this->allowSvg) {
            throw new UploadRefused('svg');
        }

        $allowed = $this->allowedTypes($rules);

        if (! in_array($extension, $allowed, true)) {
            throw new UploadRefused('type', [$extension, implode(', ', $allowed)]);
        }

        $size = (int) filesize($path);

        if ($size === 0) {
            throw new UploadRefused('empty');
        }

        $max = $this->maxSize($rules);

        if ($size > $max) {
            throw new UploadRefused('too_large', [self::megabytes($max)]);
        }

        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $accepted = $this->mimesOf($extension);

        if ($extension !== 'svg' && in_array($detected, self::ACTIVE_MIMES, true)) {
            throw new UploadRefused('content');
        }

        if (! in_array($detected, $accepted, true)) {
            throw new UploadRefused('mime', [$extension]);
        }

        $head = (string) file_get_contents($path, false, null, 0, self::HEAD_BYTES);

        if ($extension !== 'svg' && preg_match('/<\?php/i', $head) === 1) {
            throw new UploadRefused('content');
        }

        if ($extension === 'svg') {
            try {
                $clean = (new SvgSanitizer())->sanitize((string) file_get_contents($path));
            } catch (StorageException) {
                throw new UploadRefused('svg');
            }

            return new ValidatedUpload('svg', 'image/svg+xml', $clean);
        }

        if (in_array($extension, $this->config->imageExtensions, true)) {
            return $this->image($path, $extension, $accepted[0], $rules);
        }

        return new ValidatedUpload($extension, $accepted[0], null);
    }

    /**
     * @throws UploadRefused
     */
    private function image(string $path, string $extension, string $mime, UploadRules $rules): ValidatedUpload
    {
        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1 || ImageReencoder::typeOf($extension) !== $info[2]) {
            throw new UploadRefused('image');
        }

        if ($info[0] * $info[1] > $this->config->maxImagePixels) {
            throw new UploadRefused('image');
        }

        if (! $rules->reencode) {
            return new ValidatedUpload($extension, $mime, null, $info[0], $info[1]);
        }

        $image = (new ImageReencoder($this->config->imageQuality))->reencode($path, $extension);

        return new ValidatedUpload($extension, $mime, $image['contents'], $image['width'], $image['height']);
    }

    /**
     * MIME types accepted for an extension; the first is the one the file is stored with.
     *
     * @return list<string>
     */
    private function mimesOf(string $extension): array
    {
        $mimes = Mimes::$mimes[$extension] ?? [];
        $mimes = is_array($mimes) ? $mimes : [$mimes];

        return array_values(array_unique([...$mimes, ...($this->config->extraMimes[$extension] ?? [])]));
    }

    private static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }
}
