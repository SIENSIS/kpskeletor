<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Modules\Core\Archive\ArchiveRefused;
use Modules\Core\Archive\SafeZip;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Config\Packages;
use Modules\Core\Menus\MenuDefinitions;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\SemVer;
use Modules\Core\Permissions\CatalogueBuilder;
use Modules\Core\Theme\ThemeRegistry;
use ZipArchive;

/**
 * Validates a module or theme package (a ZIP) before anything is installed (docs/packages.md):
 *
 * - size of the ZIP, and its signature (`.minisig`, publisher keys of `Config\Packages`;
 *   required when `requireSignature` is on);
 * - a single top folder named like the module (`News/…`) or the theme (`corporate/…`);
 * - zip-slip, symbolic links, entry count, uncompressed size and compression ratio (SafeZip);
 * - allowed file extensions only;
 * - a manifest (`Config/Module.php` or `Theme.php`) read without running it (ManifestReader)
 *   whose name matches the folder, with a semantic version; reserved and core module names are
 *   refused (the Core modules are updated with `app:update`);
 * - every PHP file syntax-checked with `php -l` (extract()).
 *
 * Every refusal is written to the security log (`package_refused`).
 */
final class PackageValidator
{
    /** Files allowed without an extension. */
    public const BARE_NAMES = ['LICENSE', 'README', 'CHANGELOG', 'NOTICE', 'AUTHORS', 'COPYING', '.gitkeep'];

    private readonly Packages $config;

    public function __construct(
        ?Packages $config = null,
        private readonly ManifestReader $reader = new ManifestReader(),
        private readonly ?PhpLint $lint = null,
    ) {
        $this->config = $config ?? config('Packages');
    }

    public function config(): Packages
    {
        return $this->config;
    }

    /**
     * @param string|null $signature  Contents of the `.minisig` file, if any
     * @param string|null $expectType PackageInfo::MODULE or ::THEME (null: either)
     * @param string      $label      Shown in the security log (file name or URL)
     *
     * @throws PackageRefused
     */
    public function validate(string $zip, ?string $signature = null, ?string $expectType = null, string $label = ''): PackageInfo
    {
        try {
            return $this->check($zip, $signature, $expectType);
        } catch (PackageRefused|ArchiveRefused $e) {
            security_log('Core', 'package_refused', [
                'resource' => 'modules',
                'summary'  => mb_substr(($label !== '' ? $label : basename($zip)) . ': ' . $e->getMessage(), 0, 250),
            ]);

            throw $e instanceof PackageRefused ? $e : new PackageRefused($e->getMessage(), 0, $e);
        }
    }

    /**
     * Extracts a validated package into `<target>/<name>` (files 0644, folders 0755: code read
     * by the web server) and syntax-checks its PHP files; on failure nothing is left behind.
     *
     * @return string The extracted folder
     *
     * @throws PackageRefused
     */
    public function extract(string $zip, PackageInfo $info, string $target, string $label = ''): string
    {
        $folder = rtrim($target, '/') . '/' . $info->name;

        try {
            $safe = SafeZip::open($zip, [$info->name . '/'], $this->config->maxUncompressedBytes, $this->config->maxFiles, $this->config->maxRatio);

            try {
                $safe->extract($info->name . '/', $folder, 0644, 0755);
            } finally {
                $safe->close();
            }

            $php = [];

            foreach (array_keys($info->files) as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
                    $php[] = $folder . '/' . $file;
                }
            }

            ($this->lint ?? new PhpLint($this->config->phpBinary, $this->config->lintTimeout))->check($php, $folder);

            return $folder;
        } catch (PackageRefused|ArchiveRefused $e) {
            self::removeFolder($folder);
            security_log('Core', 'package_refused', [
                'resource' => 'modules',
                'summary'  => mb_substr(($label !== '' ? $label : basename($zip)) . ': ' . $e->getMessage(), 0, 250),
            ]);

            throw $e instanceof PackageRefused ? $e : new PackageRefused($e->getMessage(), 0, $e);
        }
    }

    /**
     * Validates and syntax-checks a package in a private temporary folder, then removes it.
     *
     * @throws PackageRefused
     */
    public function validateAndLint(string $zip, ?string $signature, ?string $expectType, string $work, string $label = ''): PackageInfo
    {
        $info = $this->validate($zip, $signature, $expectType, $label);
        $temp = rtrim($work, '/') . '/lint-' . bin2hex(random_bytes(6));

        try {
            $this->extract($zip, $info, $temp, $label);
        } finally {
            self::removeFolder($temp);
        }

        return $info;
    }

    private function check(string $zip, ?string $signature, ?string $expectType): PackageInfo
    {
        if (! is_file($zip)) {
            throw new PackageRefused(lang('Core.packages.unreadable'));
        }

        if ((int) filesize($zip) > $this->config->maxPackageBytes) {
            helper('number');

            throw new PackageRefused(lang('Core.packages.tooBig', [number_to_size($this->config->maxPackageBytes)]));
        }

        $signed = false;

        if ($signature !== null && trim($signature) !== '') {
            (new SignatureCheck($this->config->publicKeys))->verify($zip, $signature);
            $signed = true;
        } elseif ($this->config->requireSignature) {
            throw new PackageRefused(lang('Core.packages.signatureRequired'));
        }

        $top = $this->topFolder($zip);
        $safe = SafeZip::open($zip, [$top . '/'], $this->config->maxUncompressedBytes, $this->config->maxFiles, $this->config->maxRatio);

        try {
            $files = [];

            foreach ($safe->entries() as $name => $size) {
                $relative = substr($name, strlen($top) + 1);
                $this->checkExtension($relative);
                $files[$relative] = $size;
            }

            ksort($files);

            if (isset($files['Config/Module.php'])) {
                $type = PackageInfo::MODULE;
                $code = $safe->read($top . '/Config/Module.php', 1_048_576);
            } elseif (isset($files['Theme.php'])) {
                $type = PackageInfo::THEME;
                $code = $safe->read($top . '/Theme.php', 1_048_576);
            } else {
                throw new PackageRefused(lang('Core.packages.noManifest'));
            }
        } finally {
            $safe->close();
        }

        if ($expectType !== null && $type !== $expectType) {
            throw new PackageRefused(lang($expectType === PackageInfo::THEME ? 'Core.packages.notTheme' : 'Core.packages.notModule'));
        }

        $parsed = $this->reader->read($code);
        $sha256 = (string) hash_file('sha256', $zip);

        return $type === PackageInfo::MODULE
            ? $this->module($top, $parsed, $files, $signed, $sha256)
            : $this->theme($top, $parsed, $files, $signed, $sha256);
    }

    /**
     * The single top folder of the archive, from the raw entry names (checked again by SafeZip).
     */
    private function topFolder(string $zip): string
    {
        $archive = new ZipArchive();

        if ($archive->open($zip, ZipArchive::RDONLY) !== true) {
            throw new PackageRefused(lang('Core.archive.invalid'));
        }

        $tops = [];

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = (string) $archive->getNameIndex($i, ZipArchive::FL_UNCHANGED);
            $tops[strtok(str_replace('\\', '/', $name), '/') ?: $name] = true;
        }

        $archive->close();

        if (count($tops) !== 1) {
            throw new PackageRefused(lang('Core.packages.singleFolder'));
        }

        $top = (string) array_key_first($tops);

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $top) !== 1 && preg_match(ThemeRegistry::SLUG_PATTERN, $top) !== 1) {
            throw new PackageRefused(lang('Core.packages.badFolder', [mb_substr((string) preg_replace('/[^\x20-\x7E]/', '?', $top), 0, 60)]));
        }

        return $top;
    }

    private function checkExtension(string $relative): void
    {
        $base      = basename($relative);
        $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        $allowed = str_contains($base, '.') && ! str_starts_with($base, '.')
            ? in_array($extension, array_map('strtolower', $this->config->extensions), true)
            : in_array($base, self::BARE_NAMES, true);

        // `.phar`, `.phtml`, `.htaccess`… never; a second extension such as `x.php.txt` is fine.
        if (! $allowed) {
            throw new PackageRefused(lang('Core.packages.extension', [mb_substr($relative, 0, 200)]));
        }
    }

    /**
     * @param array{class: string, extends: string, namespace: string, properties: array<string, mixed>} $parsed
     * @param array<string, int>                                                                          $files
     */
    private function module(string $top, array $parsed, array $files, bool $signed, string $sha256): PackageInfo
    {
        $props = $parsed['properties'];
        $name  = ManifestReader::string($props, 'name');

        if ($parsed['class'] !== 'Module' || $parsed['namespace'] !== 'Modules\\' . $top . '\\Config' || ! self::extendsClass($parsed['extends'], 'ModuleManifest')) {
            throw new PackageRefused(lang('Core.packages.manifestClass', ['Modules\\' . $top . '\\Config\\Module']));
        }

        if ($name !== $top) {
            throw new PackageRefused(lang('Core.packages.nameFolder', [$name, $top]));
        }

        if (in_array($name, ManifestValidator::RESERVED_NAMES, true)) {
            throw new PackageRefused(lang('Core.packages.reserved', [$name]));
        }

        if ($name === 'Core' || in_array($name, ModuleToggler::CORE_MODULES, true)) {
            throw new PackageRefused(lang('Core.packages.coreModule', [$name]));
        }

        $version = ManifestReader::string($props, 'version');

        if (! SemVer::isVersion($version)) {
            throw new PackageRefused(lang('Core.packages.version'));
        }

        $requires = [];
        $raw      = $props['requires'] ?? [];

        if (! is_array($raw)) {
            throw new PackageRefused(lang('Core.packages.requiresInvalid'));
        }

        foreach ($raw as $module => $constraint) {
            if (! is_string($module) || ! is_string($constraint) || $constraint === ManifestReader::UNKNOWN || ! SemVer::isConstraint($constraint)) {
                throw new PackageRefused(lang('Core.packages.requiresInvalid'));
            }

            $requires[$module] = $constraint;
        }

        // A shadow manifest of the literal values, to list what the module will add.
        $shadow = new class () extends ModuleManifest {};

        foreach (['name', 'version', 'description', 'author'] as $key) {
            $shadow->{$key} = ManifestReader::string($props, $key);
        }

        foreach (['resources', 'menu', 'widgets', 'tasks'] as $key) {
            $value = ManifestReader::array($props, $key);
            $shadow->{$key} = array_filter($value, static fn ($item): bool => $item !== ManifestReader::UNKNOWN);
        }

        $resources = [];

        foreach (ManifestReader::array($props, 'resources') as $key => $definition) {
            if (is_string($key) && is_array($definition)) {
                $resources[$key] = $definition;
            }
        }

        $shadow->resources = $resources;
        $shadow->menu      = array_values(array_filter($shadow->menu, 'is_array'));
        $permissions       = [];

        try {
            $permissions = array_keys((new CatalogueBuilder())->build([$name => $shadow]));
            $menus       = array_values(array_map(static fn (array $entry): string => (string) $entry['label'], MenuDefinitions::of($shadow)));
        } catch (\Throwable) {
            $menus = [];
        }

        $classes = static fn (array $list): array => array_values(array_filter($list, static fn ($item): bool => is_string($item) && $item !== ManifestReader::UNKNOWN));

        return new PackageInfo(
            PackageInfo::MODULE,
            $name,
            $version,
            mb_substr(ManifestReader::string($props, 'author'), 0, 255),
            mb_substr(ManifestReader::string($props, 'description'), 0, 255),
            $requires,
            null,
            $permissions,
            $menus,
            $classes($shadow->widgets),
            $classes($shadow->tasks),
            $files,
            $signed,
            $sha256,
        );
    }

    /**
     * @param array{class: string, extends: string, namespace: string, properties: array<string, mixed>} $parsed
     * @param array<string, int>                                                                          $files
     */
    private function theme(string $top, array $parsed, array $files, bool $signed, string $sha256): PackageInfo
    {
        if (preg_match(ThemeRegistry::SLUG_PATTERN, $top) !== 1) {
            throw new PackageRefused(lang('Core.packages.badFolder', [$top]));
        }

        $namespace = ThemeRegistry::namespaceOf($top);

        if ($parsed['class'] !== 'Theme' || $parsed['namespace'] !== $namespace || ! self::extendsClass($parsed['extends'], 'ThemeManifest')) {
            throw new PackageRefused(lang('Core.packages.manifestClass', [$namespace . '\\Theme']));
        }

        $props   = $parsed['properties'];
        $version = ManifestReader::string($props, 'version');

        if (! SemVer::isVersion($version)) {
            throw new PackageRefused(lang('Core.packages.version'));
        }

        $parent = $props['parent'] ?? null;

        if ($parent !== null && (! is_string($parent) || preg_match(ThemeRegistry::SLUG_PATTERN, $parent) !== 1 || $parent === $top)) {
            throw new PackageRefused(lang('Core.packages.parent'));
        }

        return new PackageInfo(
            PackageInfo::THEME,
            $top,
            $version,
            mb_substr(ManifestReader::string($props, 'author'), 0, 255),
            mb_substr(ManifestReader::string($props, 'name'), 0, 255),
            [],
            $parent,
            [],
            [],
            [],
            [],
            $files,
            $signed,
            $sha256,
        );
    }

    private static function extendsClass(string $extends, string $short): bool
    {
        $extends = ltrim($extends, '\\');

        return $extends === $short || str_ends_with($extends, '\\' . $short);
    }

    public static function removeFolder(string $folder): void
    {
        if (! is_dir($folder) || is_link($folder)) {
            if (is_link($folder)) {
                @unlink($folder);
            }

            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($folder);
    }
}
