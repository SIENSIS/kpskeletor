<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use Config\Services;

/**
 * Finds and loads the themes in `themes/`.
 *
 * A theme lives in `themes/<slug>/` (lower-case letters, digits and hyphens, e.g. `default`,
 * `tasks-demo`) and its manifest is `Themes\<Studly>\Theme` (e.g. `Themes\TasksDemo\Theme`).
 * The theme's PSR-4 namespace is registered with the autoloader when the theme is first loaded,
 * so the active theme's views, classes and language files are found without scanning `themes/`
 * on each request. Only the console and the settings screen list every theme (slugs()).
 */
final class ThemeRegistry
{
    public const SLUG_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /** Maximum depth of a parent chain (child → parent → …). */
    public const MAX_DEPTH = 5;

    /** @var array<string, ThemeManifest> path => manifest */
    private static array $loaded = [];

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? ROOTPATH . 'themes', '/\\');
    }

    public function root(): string
    {
        return $this->root;
    }

    public static function isValidSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    /** "tasks-demo" → "TasksDemo". */
    public static function studly(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }

    /** "TasksDemo" → "tasks-demo". */
    public static function slugFromName(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name));
    }

    /** "tasks-demo" → "Themes\TasksDemo". */
    public static function namespaceOf(string $slug): string
    {
        return 'Themes\\' . self::studly($slug);
    }

    public function path(string $slug): string
    {
        return $this->root . DIRECTORY_SEPARATOR . $slug;
    }

    public function exists(string $slug): bool
    {
        return self::isValidSlug($slug) && is_file($this->path($slug) . DIRECTORY_SEPARATOR . 'Theme.php');
    }

    /**
     * Every theme folder with a manifest file, sorted (console and settings only).
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $slugs = [];

        foreach (glob($this->root . '/*/Theme.php') ?: [] as $file) {
            $slug = basename(dirname($file));

            if (self::isValidSlug($slug)) {
                $slugs[] = $slug;
            }
        }

        sort($slugs);

        return $slugs;
    }

    /**
     * Loads a theme manifest and registers the theme's namespace.
     *
     * @throws ThemeNotFoundException
     */
    public function get(string $slug): ThemeManifest
    {
        if (! $this->exists($slug)) {
            throw ThemeNotFoundException::forSlug($slug);
        }

        $path      = $this->path($slug);
        $namespace = self::namespaceOf($slug);

        // Checked on every call: the autoloader service may have been replaced (tests).
        $this->registerNamespace($namespace, $path);

        if (isset(self::$loaded[$path])) {
            return self::$loaded[$path];
        }

        $class = $namespace . '\\Theme';

        if (! class_exists($class)) {
            require_once $path . DIRECTORY_SEPARATOR . 'Theme.php';
        }

        if (! class_exists($class, false) || ! is_subclass_of($class, ThemeManifest::class)) {
            throw ThemeNotFoundException::forManifest($slug, $class);
        }

        return self::$loaded[$path] = new $class();
    }

    /**
     * The theme and its ancestors, the theme first (e.g. ['corporate', 'default']).
     *
     * @return list<string>
     *
     * @throws ThemeNotFoundException when a theme of the chain is missing or the chain loops
     */
    public function chain(string $slug): array
    {
        $chain = [];

        for ($current = $slug; $current !== null; $current = $this->get($current)->parent) {
            if (in_array($current, $chain, true) || count($chain) >= self::MAX_DEPTH) {
                throw ThemeNotFoundException::forCycle(implode(' → ', [...$chain, $current]));
            }

            $this->get($current);
            $chain[] = $current;
        }

        return $chain;
    }

    /** Forgets the loaded manifests (tests). */
    public static function reset(): void
    {
        self::$loaded = [];
    }

    private function registerNamespace(string $namespace, string $path): void
    {
        $autoloader = Services::autoloader();
        $paths      = $autoloader->getNamespace($namespace);
        $real       = realpath($path);

        foreach ($paths as $registered) {
            if (realpath($registered) === $real) {
                return;
            }
        }

        $autoloader->addNamespace($namespace, $path);
    }
}
