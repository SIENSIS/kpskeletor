<?php

declare(strict_types=1);

namespace Modules\Core\Registry;

use CodeIgniter\Router\RouteCollection;
use Config\Modules as ModulesConfig;
use Modules\Core\Config\ModuleManifest;

/**
 * Checks that every `$api` entry of a manifest matches a route of the module's
 * `Config/Routes.php`: `api/v1/<path>` handled by `<handler>` (decision D-00-08). Run by
 * `modules:sync` before anything is written; `permissions:check` (session 07) checks more.
 */
final class ApiRouteChecker
{
    public const PREFIX = 'api/v1/';

    private const VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param array<string, ModuleManifest> $manifests
     *
     * @return list<string> Errors (translated)
     */
    public function check(array $manifests): array
    {
        $entries = [];

        foreach ($manifests as $module => $manifest) {
            foreach ($manifest->api as $key => $definition) {
                if (is_string($definition['path'] ?? null) && is_string($definition['handler'] ?? null)) {
                    $entries[] = [$module, (string) $key, $definition['path'], $definition['handler']];
                }
            }
        }

        if ($entries === []) {
            return [];
        }

        $handlers = $this->routes();
        $errors   = [];

        foreach ($entries as [$module, $key, $path, $handler]) {
            $route = self::PREFIX . $path;

            if (! in_array(self::normalise($handler), $handlers[$route] ?? [], true)) {
                $errors[] = lang('Core.manifest.error', [$module, "\$api['{$key}']", lang('Core.manifest.apiRoute', [$route, $handler])]);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, list<string>> route => handlers (normalised)
     */
    private function routes(): array
    {
        $collection = new RouteCollection(service('locator'), new ModulesConfig(), config('Routing'));
        $collection->loadRoutes();

        $routes = [];

        foreach (self::VERBS as $verb) {
            foreach ($collection->getRoutes($verb, false) as $from => $to) {
                if (is_string($to)) {
                    $routes[(string) $from][] = self::normalise($to);
                }
            }
        }

        return $routes;
    }

    private static function normalise(string $handler): string
    {
        $handler = ltrim($handler, '\\');
        $slash   = strpos($handler, '/');

        return $slash === false ? $handler : substr($handler, 0, $slash);
    }
}
