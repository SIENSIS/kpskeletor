<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies each theme's `Assets/` folder to `public/themes/<slug>/` (run by `modules:sync` and
 * `make:theme`). Only changed files are written; files no longer in the source are removed.
 * Published copies are never edited by hand. The front-end session folds this into
 * `assets:publish` (hashes, asset manifest).
 */
final class ThemeAssetPublisher
{
    /** Extensions a theme may publish (no PHP, no HTML). */
    public const EXTENSIONS = ['css', 'js', 'mjs', 'map', 'json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'woff', 'woff2'];

    private readonly string $target;

    public function __construct(
        private readonly ThemeRegistry $registry = new ThemeRegistry(),
        ?string $target = null,
    ) {
        $this->target = rtrim($target ?? FCPATH . 'themes', '/\\');
    }

    /**
     * @return array<string, int> slug => number of files written or removed
     */
    public function publishAll(): array
    {
        $changes = [];

        foreach ($this->registry->slugs() as $slug) {
            $changes[$slug] = $this->publish($slug);
        }

        return $changes;
    }

    public function publish(string $slug): int
    {
        if (! ThemeRegistry::isValidSlug($slug)) {
            return 0;
        }

        $source  = $this->registry->path($slug) . '/Assets';
        $target  = $this->target . '/' . $slug;
        $changed = 0;
        $kept    = [];

        if (is_dir($source)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                /** @var SplFileInfo $file */
                if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                $copy     = $target . '/' . $relative;
                $kept[]   = $relative;

                if (is_file($copy) && hash_file('sha256', $copy) === hash_file('sha256', $file->getPathname())) {
                    continue;
                }

                if (! is_dir(dirname($copy))) {
                    mkdir(dirname($copy), 0755, true);
                }

                copy($file->getPathname(), $copy);
                $changed++;
            }
        }

        if (is_dir($target)) {
            $published = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($published as $file) {
                /** @var SplFileInfo $file */
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1));

                if ($file->isFile() && ! in_array($relative, $kept, true)) {
                    unlink($file->getPathname());
                    $changed++;
                } elseif ($file->isDir() && (scandir($file->getPathname()) ?: []) === ['.', '..']) {
                    rmdir($file->getPathname());
                }
            }
        }

        return $changed;
    }
}
