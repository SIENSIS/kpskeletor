<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use CodeIgniter\Publisher\Publisher;
use FilesystemIterator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Theme\ThemeRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * `assets:publish`: copies the front-end sources to the public folder with CodeIgniter's
 * Publisher and writes the asset manifest (`public/assets/manifest.json`, file hashes).
 *
 * - Core: `modules/Core/Assets/` → `public/assets/core/`;
 * - each module: `modules/<Name>/Assets/` → `public/assets/modules/<name>/` (`TasksDemo` →
 *   `tasks-demo`);
 * - each theme: `themes/<slug>/Assets/` → `public/themes/<slug>/`.
 *
 * Only files with an allowed extension are published (never PHP or HTML), symbolic links are
 * skipped, changed files are copied and files no longer in a source are removed, so the public
 * copies always mirror the sources (they are git-ignored and never edited by hand). Run by
 * `modules:sync`, `modules:enable`, `make:theme` and on deploy; in development the asset
 * manifest republishes by itself when a source is newer (AssetManifest).
 */
final class AssetPublisher
{
    /** Extensions that may be published. */
    public const EXTENSIONS = ['css', 'js', 'mjs', 'map', 'json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'woff', 'woff2'];

    /** Owner names a module cannot take (`core::`, `theme::`, `vendor::` in asset()). */
    public const RESERVED = ['core', 'theme', 'vendor'];

    private readonly string $public;

    private readonly string $modulesRoot;

    private readonly ThemeRegistry $themes;

    /**
     * @param string|null        $public      public folder (default FCPATH)
     * @param string|null        $modulesRoot folder that contains the modules (default ROOTPATH/modules)
     * @param ThemeRegistry|null $themes      themes (default ROOTPATH/themes)
     */
    public function __construct(?string $public = null, ?string $modulesRoot = null, ?ThemeRegistry $themes = null)
    {
        $this->public      = rtrim($public ?? FCPATH, '/\\') . '/';
        $this->modulesRoot = rtrim($modulesRoot ?? ROOTPATH . 'modules', '/\\');
        $this->themes      = $themes ?? new ThemeRegistry();
    }

    public function manifestPath(): string
    {
        return $this->public . AssetManifest::FILE;
    }

    /** "TasksDemo" → "tasks-demo" (the asset owner and import-map prefix of a module). */
    public static function ownerOf(string $module): string
    {
        return $module === 'Core' ? 'core' : ThemeRegistry::slugFromName($module);
    }

    /**
     * The folders to publish: owner => [source, target, url prefix].
     *
     * @return array<string, array{source: string, target: string, url: string}>
     */
    public function sources(): array
    {
        $sources = [];
        $modules = (new ModuleDiscovery([$this->modulesRoot]))->folders();

        foreach ($modules as $name => $path) {
            $owner = self::ownerOf($name);

            if ($name !== 'Core' && in_array($owner, self::RESERVED, true)) {
                log_message('warning', 'The assets of module {module} are not published: "{owner}" is reserved.', ['module' => $name, 'owner' => $owner]);

                continue;
            }

            $url             = $name === 'Core' ? 'assets/core' : 'assets/modules/' . $owner;
            $sources[$owner] = ['source' => $path . '/Assets', 'target' => $this->public . $url, 'url' => $url];
        }

        foreach ($this->themes->slugs() as $slug) {
            $sources['theme:' . $slug] = [
                'source' => $this->themes->path($slug) . '/Assets',
                'target' => $this->public . 'themes/' . $slug,
                'url'    => 'themes/' . $slug,
            ];
        }

        return $sources;
    }

    /**
     * Publishes every source and writes the manifest.
     *
     * @return array<string, int> owner => number of files written or removed (only owners with changes)
     */
    public function publish(?string $only = null): array
    {
        $changes = [];
        $sources = $this->sources();
        $files   = [];

        if ($only !== null) {
            // Keep the other owners' entries, but only of owners that still exist.
            foreach ((new AssetManifest($this->manifestPath()))->files() as $key => $entry) {
                if (isset($sources[explode('::', $key, 2)[0]])) {
                    $files[$key] = $entry;
                }
            }
        }

        foreach ($sources as $owner => $source) {
            if ($only !== null && $owner !== $only) {
                continue;
            }

            if ($only !== null) {
                $files = array_filter($files, static fn (string $key): bool => ! str_starts_with($key, $owner . '::'), ARRAY_FILTER_USE_KEY);
            }

            [$changed, $entries] = $this->mirror($source['source'], $source['target'], $source['url']);

            foreach ($entries as $relative => $entry) {
                $files[$owner . '::' . $relative] = $entry;
            }

            if ($changed > 0) {
                $changes[$owner] = $changed;
            }
        }

        $this->removeOrphans($only);

        if ($this->writeManifest($files)) {
            $changes['manifest'] = 1;
        }

        return $changes;
    }

    /**
     * What a publish would change, without writing anything (`assets:publish --check`).
     *
     * @return list<string> public paths that are missing, outdated or stale, and the manifest
     */
    public function check(): array
    {
        $problems = [];
        $files    = [];

        foreach ($this->sources() as $owner => $source) {
            $expected = $this->scan($source['source']);

            foreach ($expected as $relative => $path) {
                $copy = $source['target'] . '/' . $relative;

                if (! is_file($copy) || hash_file('sha256', $copy) !== hash_file('sha256', $path)) {
                    $problems[] = $source['url'] . '/' . $relative;
                }

                $files[$owner . '::' . $relative] = ['url' => $source['url'] . '/' . $relative, 'hash' => self::hash($path)];
            }

            foreach (array_keys($this->scan($source['target'], false)) as $relative) {
                if (! isset($expected[$relative])) {
                    $problems[] = $source['url'] . '/' . $relative;
                }
            }
        }

        ksort($files);
        $manifest = is_file($this->manifestPath()) ? (string) file_get_contents($this->manifestPath()) : '';

        if ($manifest !== self::encode($files)) {
            $problems[] = AssetManifest::FILE;
        }

        return $problems;
    }

    /**
     * Whether a source changed after the manifest was written (development refresh): a newer
     * file or folder (files added or removed change their folder's time).
     */
    public function isStale(): bool
    {
        $manifest = $this->manifestPath();

        if (! is_file($manifest)) {
            return true;
        }

        $written = (int) filemtime($manifest);

        foreach ($this->sources() as $source) {
            if (! is_dir($source['source'])) {
                continue;
            }

            if ((int) filemtime($source['source']) > $written) {
                return true;
            }

            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source['source'], FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($items as $item) {
                /** @var SplFileInfo $item */
                if ($item->getMTime() > $written) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Short content hash used in the asset URLs (`?v=`). */
    public static function hash(string $file): string
    {
        return substr((string) hash_file('sha256', $file), 0, 12);
    }

    /**
     * @param array<string, array{url: string, hash: string}> $files
     */
    public static function encode(array $files): string
    {
        return json_encode(['version' => 1, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Copies one source folder (CodeIgniter Publisher) and removes stale copies.
     *
     * @return array{0: int, 1: array<string, array{url: string, hash: string}>}
     */
    private function mirror(string $source, string $target, string $url): array
    {
        $files   = $this->scan($source);
        $changed = 0;
        $entries = [];

        foreach ($files as $relative => $path) {
            $copy = $target . '/' . $relative;

            if (! is_file($copy) || hash_file('sha256', $copy) !== hash_file('sha256', $path)) {
                $changed++;
            }

            $entries[$relative] = ['url' => $url . '/' . $relative, 'hash' => self::hash($path)];
        }

        if ($files !== []) {
            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }

            (new Publisher(realpath($source) ?: $source, $target))->addFiles(array_values($files))->merge(true);
        }

        foreach ($this->scan($target, false) as $relative => $path) {
            if (! isset($files[$relative])) {
                unlink($path);
                $changed++;
            }
        }

        $this->removeEmptyFolders($target);

        return [$changed, $entries];
    }

    /**
     * Files of a folder by relative path (sorted). Sources: allowed extensions only, no links.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function scan(string $folder, bool $filter = true): array
    {
        if (! is_dir($folder)) {
            return [];
        }

        $folder = realpath($folder) ?: $folder;
        $files  = [];
        $items  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            if (! $item->isFile()) {
                continue;
            }

            if ($filter && ($item->isLink() || ! in_array(strtolower($item->getExtension()), self::EXTENSIONS, true))) {
                continue;
            }

            $relative         = str_replace('\\', '/', substr($item->getPathname(), strlen($folder) + 1));
            $files[$relative] = $item->getPathname();
        }

        ksort($files);

        return $files;
    }

    /**
     * Removes published folders whose module or theme no longer exists.
     */
    private function removeOrphans(?string $only): void
    {
        if ($only !== null) {
            return;
        }

        $known = array_column($this->sources(), 'target');

        foreach ([$this->public . 'assets/modules', $this->public . 'themes'] as $parent) {
            foreach (glob($parent . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
                if (! in_array($folder, $known, true)) {
                    foreach ($this->scan($folder, false) as $path) {
                        unlink($path);
                    }

                    $this->removeEmptyFolders($folder);

                    if (is_dir($folder)) {
                        @rmdir($folder);
                    }
                }
            }
        }
    }

    private function removeEmptyFolders(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir() && (scandir($item->getPathname()) ?: []) === ['.', '..']) {
                rmdir($item->getPathname());
            }
        }
    }

    /**
     * @param array<string, array{url: string, hash: string}> $files
     */
    private function writeManifest(array $files): bool
    {
        ksort($files);
        $json = self::encode($files);
        $path = $this->manifestPath();

        if (is_file($path) && file_get_contents($path) === $json) {
            touch($path); // the sources were checked now (isStale())

            return false;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $json, LOCK_EX);
        AssetManifest::reset();

        return true;
    }
}
