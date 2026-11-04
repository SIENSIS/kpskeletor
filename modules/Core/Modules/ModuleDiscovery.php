<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Autoloader\Autoloader;
use Modules\Core\Config\ModuleManifest;

/**
 * Finds module folders (`<root>/<Name>/Config/Module.php`) and loads their manifests.
 *
 * Only used by console commands and the Modules screen (sync time), never on regular requests:
 * at runtime the namespaces come from the generated `app/Config/ModuleNamespaces.php`.
 */
final class ModuleDiscovery
{
    /** @var list<string> */
    private array $roots;

    /**
     * @param list<string>|null $roots Folders that contain modules (default: ROOTPATH/modules)
     */
    public function __construct(?array $roots = null, private readonly ?Autoloader $autoloader = null)
    {
        $this->roots = array_map(
            static fn (string $root): string => rtrim($root, '/\\'),
            $roots ?? [ROOTPATH . 'modules'],
        );
    }

    /**
     * Module folders by name, sorted by name (no manifest is loaded).
     *
     * @return array<string, string> name => absolute path
     */
    public function folders(): array
    {
        $folders = [];

        foreach ($this->roots as $root) {
            foreach (glob($root . '/*/Config/Module.php') ?: [] as $file) {
                $path = dirname($file, 2);
                $name = basename($path);

                if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name) === 1 && ! isset($folders[$name])) {
                    $folders[$name] = $path;
                }
            }
        }

        ksort($folders);

        return $folders;
    }

    /**
     * Discovers every module, registers its namespace in the running autoloader and validates
     * its manifest.
     *
     * @return array<string, DiscoveredModule> name => module, sorted by name
     */
    public function discover(?ManifestValidator $validator = null): array
    {
        $validator ??= new ManifestValidator();
        $autoloader = $this->autoloader ?? service('autoloader');
        $modules    = [];

        foreach ($this->folders() as $name => $path) {
            $namespace = 'Modules\\' . $name;
            $this->register($autoloader, $namespace, $path);

            $class    = $namespace . '\\Config\\Module';
            $manifest = class_exists($class) ? new $class() : null;

            if (! $manifest instanceof ModuleManifest) {
                $modules[$name] = new DiscoveredModule($name, $path, $namespace, null, [
                    lang('Core.manifest.error', [$name, 'Config/Module.php', lang('Core.manifest.notManifest', [$class])]),
                ]);

                continue;
            }

            $modules[$name] = new DiscoveredModule($name, $path, $namespace, $manifest, $validator->validate($manifest, $path));
        }

        return $modules;
    }

    /**
     * Adds a namespace to the running autoloader unless it already points at the same folder
     * (a duplicate path would make CodeIgniter list every migration twice).
     */
    private function register(Autoloader $autoloader, string $namespace, string $path): void
    {
        $real = realpath($path);

        foreach ($autoloader->getNamespace($namespace) as $known) {
            if (realpath($known) === $real) {
                return;
            }
        }

        $autoloader->addNamespace($namespace, $path);
    }
}
