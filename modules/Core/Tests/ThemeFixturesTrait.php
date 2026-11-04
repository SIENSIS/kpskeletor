<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Theme\ThemeRegistry;

/**
 * Fixture themes for tests, in a temporary folder or (for child themes of the real default
 * theme) in themes/; removed afterwards. Slugs are unique per run so their classes never clash.
 */
trait ThemeFixturesTrait
{
    private string $themesRoot = '';

    /** @var list<string> */
    private array $realThemeFixtures = [];

    private function themesRoot(): string
    {
        if ($this->themesRoot === '') {
            $this->themesRoot = sys_get_temp_dir() . '/kps-themes-' . bin2hex(random_bytes(6));
            mkdir($this->themesRoot, 0777, true);
        }

        return $this->themesRoot;
    }

    private function uniqueSlug(string $prefix = 'fx'): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Writes a theme: its manifest (with $body as the class body) and the given files.
     *
     * @param array<string, string> $files path relative to the theme => content
     */
    private function makeTheme(string $slug, ?string $parent, string $body = '', array $files = [], ?string $root = null): string
    {
        $root ??= $this->themesRoot();
        $path      = $root . '/' . $slug;
        $namespace = ThemeRegistry::namespaceOf($slug);
        $parentPhp = $parent === null ? 'null' : var_export($parent, true);
        $version   = str_contains($body, '$version') ? '' : "public string \$version = '1.0.0';";

        $files['Theme.php'] = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Modules\\Core\\Theme\\ThemeManifest;

            final class Theme extends ThemeManifest
            {
                public string \$name = '{$slug}';
                {$version}
                public ?string \$parent = {$parentPhp};
                {$body}
            }
            PHP;

        foreach ($files as $file => $content) {
            if (! is_dir(dirname($path . '/' . $file))) {
                mkdir(dirname($path . '/' . $file), 0777, true);
            }

            file_put_contents($path . '/' . $file, $content);
        }

        return $path;
    }

    /**
     * A standalone theme with every layout (each prints its name and the content section).
     *
     * @param array<string, string> $files
     */
    private function makeBaseTheme(string $slug, string $body = '', array $files = []): string
    {
        foreach (['backend', 'public', 'auth', 'blank', 'print'] as $layout) {
            $files["Views/layouts/{$layout}.php"] ??= "[{$slug}:{$layout}]<?= \$this->renderSection('content') ?>";
        }

        return $this->makeTheme($slug, null, "public array \$layouts = ['backend', 'public', 'auth', 'blank', 'print'];\n" . $body, $files);
    }

    /**
     * A child of the real default theme, in themes/ (removed in tearDown).
     *
     * @param array<string, string> $files
     */
    private function makeDefaultChild(string $slug, string $body = '', array $files = []): string
    {
        $this->realThemeFixtures[] = $slug;

        return $this->makeTheme($slug, 'default', $body, $files, ROOTPATH . 'themes');
    }

    private function removeThemeFixtures(): void
    {
        helper('filesystem');

        $folders = array_map(static fn (string $slug): string => ROOTPATH . 'themes/' . $slug, $this->realThemeFixtures);
        $folders = [...$folders, ...array_map(static fn (string $slug): string => FCPATH . 'themes/' . $slug, $this->realThemeFixtures)];

        if ($this->themesRoot !== '') {
            $folders[] = $this->themesRoot;
        }

        foreach ($folders as $folder) {
            if (is_dir($folder)) {
                delete_files($folder, true, false, true);
                rmdir($folder);
            }
        }

        $published               = $this->realThemeFixtures !== [];
        $this->themesRoot        = '';
        $this->realThemeFixtures = [];
        ThemeRegistry::reset();

        if ($published && is_file(FCPATH . AssetManifest::FILE)) {
            (new AssetPublisher())->publish(); // drop the fixtures from the real asset manifest
        }
    }
}
