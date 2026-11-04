<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use Closure;
use Throwable;

/**
 * Reads the asset manifest written by AssetPublisher (`public/assets/manifest.json`): published
 * URL and content hash of every file, keyed `<owner>::<path>` (`core::js/app.js`,
 * `news::css/news.css`, `theme:default::css/theme.css`).
 *
 * In development (Config\Assets::$devRefresh), the manifest republishes the assets first when a
 * source is newer than the manifest, so edits in `modules/<Name>/Assets` show up without running
 * `assets:publish`. Production only reads the file (once per request).
 */
final class AssetManifest
{
    public const FILE = 'assets/manifest.json';

    /** @var array<string, array<string, array{url: string, hash: string}>> path => files */
    private static array $cache = [];

    /** @var array<string, string>|null vendor file => hash */
    private static ?array $vendor = null;

    private readonly string $path;

    /**
     * @param string|null       $path    manifest file (default FCPATH/assets/manifest.json)
     * @param (Closure(): void)|null $refresh called before the first read (development refresh)
     */
    public function __construct(?string $path = null, private readonly ?Closure $refresh = null)
    {
        $this->path = $path ?? FCPATH . self::FILE;
    }

    /**
     * The shared manifest of this request; in development it refreshes stale assets first.
     */
    public static function create(): self
    {
        $refresh = null;

        if (ENVIRONMENT === 'development' && config(\Config\Assets::class)->devRefresh) {
            $refresh = static function (): void {
                try {
                    $publisher = new AssetPublisher();

                    if ($publisher->isStale()) {
                        $publisher->publish();
                    }
                } catch (Throwable $e) {
                    log_message('error', 'Assets could not be republished: {message}', ['message' => $e->getMessage()]);
                }
            };
        }

        return new self(null, $refresh);
    }

    public static function reset(): void
    {
        self::$cache  = [];
        self::$vendor = null;
    }

    /**
     * @return array<string, array{url: string, hash: string}>
     */
    public function files(): array
    {
        if (isset(self::$cache[$this->path])) {
            return self::$cache[$this->path];
        }

        if ($this->refresh !== null) {
            ($this->refresh)();
        }

        $files = [];

        if (is_file($this->path)) {
            $data = json_decode((string) file_get_contents($this->path), true);

            foreach (is_array($data) && is_array($data['files'] ?? null) ? $data['files'] : [] as $key => $entry) {
                if (is_string($key) && is_array($entry) && is_string($entry['url'] ?? null) && is_string($entry['hash'] ?? null)) {
                    $files[$key] = ['url' => $entry['url'], 'hash' => $entry['hash']];
                }
            }
        }

        return self::$cache[$this->path] = $files;
    }

    /**
     * @return array{url: string, hash: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->files()[$key] ?? null;
    }

    /**
     * Versioned URL of a published file (`core::js/app.js` → `/assets/core/js/app.js?v=…`).
     */
    public function url(string $key): ?string
    {
        $entry = $this->get($key);

        return $entry === null ? null : base_url($entry['url']) . '?v=' . $entry['hash'];
    }

    /**
     * Versioned URL of a vendored file (`public/assets/vendor/<path>`), hash from vendor.json.
     */
    public function vendorUrl(string $path): ?string
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || ! is_file(FCPATH . 'assets/vendor/' . $path)) {
            return null;
        }

        $hash = $this->vendorHashes()[$path] ?? null;

        return base_url('assets/vendor/' . $path) . ($hash === null ? '' : '?v=' . $hash);
    }

    /**
     * Files of one owner whose path starts with a prefix (`js/`): relative path => entry.
     *
     * @return array<string, array{url: string, hash: string}>
     */
    public function filesOf(string $owner, string $prefix = ''): array
    {
        $files = [];
        $start = $owner . '::' . $prefix;

        foreach ($this->files() as $key => $entry) {
            if (str_starts_with($key, $start)) {
                $files[substr($key, strlen($start))] = $entry;
            }
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function vendorHashes(): array
    {
        if (self::$vendor !== null) {
            return self::$vendor;
        }

        $hashes = [];
        $file   = FCPATH . 'assets/vendor/vendor.json';
        $data   = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        foreach (is_array($data) ? $data : [] as $library => $entry) {
            foreach (is_array($entry['files'] ?? null) ? $entry['files'] : [] as $path => $hash) {
                $hashes[$library . '/' . $path] = substr((string) $hash, 0, 12);
            }
        }

        return self::$vendor = $hashes;
    }
}
