<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Packages\ManifestReader;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Theme\ThemeRegistry;

/**
 * A module (`modules/<Name>`) or theme (`themes/<slug>`) folder that can be packaged: its name,
 * folder, manifest file and version, read without running the manifest (the Core's
 * `ManifestReader`, as the installer reads it). The Core modules are not packaged: they are
 * updated with `app:update` (the installer refuses them).
 */
final class PackageSource
{
    /**
     * @param 'module'|'theme' $type
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $folder,
        public readonly string $version,
        public readonly string $description = '',
    ) {
    }

    public function isModule(): bool
    {
        return $this->type === PackageInfo::MODULE;
    }

    public function manifest(): string
    {
        return $this->folder . ($this->isModule() ? '/Config/Module.php' : '/Theme.php');
    }

    /** `<Name>-<version>.zip` */
    public function fileName(): string
    {
        return $this->name . '-' . $this->version . '.zip';
    }

    /** minisign's trusted comment (D-43-01). */
    public function trustedComment(): string
    {
        return $this->name . ' ' . $this->version;
    }

    public static function root(): string
    {
        return rtrim(ROOTPATH, '/');
    }

    /**
     * @throws PackageBuildFailed when there is no such module or theme
     */
    public static function find(string $type, string $name, ?string $root = null): self
    {
        $root ??= self::root();

        if ($type === PackageInfo::MODULE) {
            if (preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $name) !== 1 || $name === 'Core' || in_array($name, ModuleToggler::CORE_MODULES, true)) {
                throw new PackageBuildFailed(lang('Devtools.packages.errors.noSource', [$name]));
            }
        } elseif (preg_match(ThemeRegistry::SLUG_PATTERN, $name) !== 1) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.noSource', [$name]));
        }

        $source = self::read($type, $root . ($type === PackageInfo::MODULE ? '/modules/' : '/themes/') . $name, $name);

        if ($source === null) {
            throw new PackageBuildFailed(lang('Devtools.packages.errors.noSource', [$name]));
        }

        return $source;
    }

    /**
     * Every module that can be packaged (not the Core modules) and every theme.
     *
     * @return list<self>
     */
    public static function all(?string $root = null): array
    {
        $root ??= self::root();
        $list = [];

        foreach (glob($root . '/modules/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $name = basename($folder);

            if ($name === 'Core' || in_array($name, ModuleToggler::CORE_MODULES, true) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
                continue;
            }

            $source = self::read(PackageInfo::MODULE, $folder, $name);

            if ($source !== null) {
                $list[] = $source;
            }
        }

        foreach (glob($root . '/themes/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $name = basename($folder);

            if (preg_match(ThemeRegistry::SLUG_PATTERN, $name) !== 1) {
                continue;
            }

            $source = self::read(PackageInfo::THEME, $folder, $name);

            if ($source !== null) {
                $list[] = $source;
            }
        }

        return $list;
    }

    private static function read(string $type, string $folder, string $name): ?self
    {
        $manifest = $folder . ($type === PackageInfo::MODULE ? '/Config/Module.php' : '/Theme.php');

        if (! is_file($manifest) || is_link($folder)) {
            return null;
        }

        try {
            $props = (new ManifestReader())->read((string) file_get_contents($manifest))['properties'];
        } catch (\Throwable) {
            return null;
        }

        return new self(
            $type,
            $name,
            $folder,
            ManifestReader::string($props, 'version'),
            ManifestReader::string($props, $type === PackageInfo::MODULE ? 'description' : 'name'),
        );
    }
}
