<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

/**
 * The vendored front-end libraries (`public/assets/vendor/<lib>/`) against
 * `public/assets/vendor/vendor.json`: every listed file exists with its SHA-256, and no library
 * folder holds a file the manifest does not list. Used by `assets:publish --check` (CI) and the
 * `dependencies.vendor` security check.
 */
final class VendorChecksums
{
    public const MANIFEST = 'vendor.json';

    private readonly string $root;

    /**
     * @param string|null $root folder holding vendor.json and the library folders (default FCPATH/assets/vendor)
     */
    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? FCPATH . 'assets/vendor', '/\\');
    }

    /**
     * Whether vendor.json exists and is a JSON object.
     */
    public function hasManifest(): bool
    {
        return $this->manifest() !== null;
    }

    /**
     * Files listed in vendor.json.
     */
    public function fileCount(): int
    {
        $count = 0;

        foreach ($this->manifest() ?? [] as $entry) {
            $count += count($this->filesOf($entry));
        }

        return $count;
    }

    /**
     * Problems, as paths relative to the vendor folder (`<lib>/<file>`): changed or missing
     * files, files not listed (`+ <lib>/<file>`), or `vendor.json` when it is missing or invalid.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $manifest = $this->manifest();

        if ($manifest === null) {
            return [self::MANIFEST];
        }

        $problems = [];

        foreach ($manifest as $library => $entry) {
            $listed = $this->filesOf($entry);

            foreach ($listed as $path => $hash) {
                $file = $this->root . '/' . $library . '/' . $path;

                if (! is_file($file) || ! hash_equals($hash, (string) hash_file('sha256', $file))) {
                    $problems[] = $library . '/' . $path;
                }
            }

            foreach ($this->scan($this->root . '/' . $library) as $relative) {
                if (! isset($listed[$relative])) {
                    $problems[] = '+ ' . $library . '/' . $relative;
                }
            }
        }

        return $problems;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifest(): ?array
    {
        $json = @file_get_contents($this->root . '/' . self::MANIFEST);

        if ($json === false) {
            return null;
        }

        $manifest = json_decode($json, true);

        return is_array($manifest) && ! array_is_list($manifest) ? $manifest : null;
    }

    /**
     * @return array<string, string> path => SHA-256
     */
    private function filesOf(mixed $entry): array
    {
        $files = is_array($entry) && is_array($entry['files'] ?? null) ? $entry['files'] : [];
        $out   = [];

        foreach ($files as $path => $hash) {
            $out[(string) $path] = (string) $hash;
        }

        return $out;
    }

    /**
     * @return list<string> files below $folder, relative, with forward slashes
     */
    private function scan(string $folder): array
    {
        if (! is_dir($folder)) {
            return [];
        }

        $files = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
        }

        sort($files);

        return $files;
    }
}
