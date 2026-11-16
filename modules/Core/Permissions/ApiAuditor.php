<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Router\RouteCollection;
use Kpapy\OpenApi\Generator;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Registry\ApiRouteChecker;

/**
 * Compares every kpapy resource of the routes with the manifests and the permission catalogue
 * (`php spark permissions:check`, session 26), the API counterpart of `CrudAuditor`:
 *
 * - every resource route (`Resource::routes()` marks them with the `kpapy` route option; public
 *   resources set it by hand) is declared in a manifest's `$api` (`ApiRouteChecker` checks the
 *   other direction);
 * - its handler renders a `Kpapy\Resource` (kpapy's describe mode, the same one that builds the
 *   OpenAPI document, so nothing runs against the data);
 * - every operation the resource can ask about (`permissionCatalogue()`, custom operations
 *   included) is declared in the catalogue. Public resources (`kpapy:public`/`kpapy:optional`)
 *   ask no resolver and are skipped.
 *
 * Like `CrudAuditor` it runs without a user; `CrudPermissions` still fails loudly outside
 * production the first time a resource asks about an undeclared operation.
 */
final class ApiAuditor
{
    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * @param array<string, ModuleManifest> $manifests
     *
     * @return list<string> One line per problem: "API <route>: <problem>"
     */
    public function audit(RouteCollection $routes, array $manifests): array
    {
        $declared = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->api as $definition) {
                if (is_string($definition['path'] ?? null)) {
                    $declared[ApiRouteChecker::PREFIX . trim($definition['path'], '/')] = true;
                }
            }
        }

        $generator = new Generator(null, $routes);
        $resolver  = CrudPermissions::forUser(null, $this->catalogue);
        $problems  = [];

        foreach ($generator->resourceRoutes() as $path => $route) {
            $path = ltrim((string) $path, '/');

            if (! str_starts_with($path, ApiRouteChecker::PREFIX)) {
                continue;
            }

            if (! isset($declared[$path])) {
                $problems[] = "API {$path}: the resource is not declared in any manifest's \$api.";
            }

            $warnings   = count($generator->warnings);
            $definition = $generator->describe($route['handler'], $path);

            if (! is_array($definition) || ! is_string($definition['resource'] ?? null)) {
                $error      = array_slice($generator->warnings, $warnings)[0] ?? null;
                $problems[] = "API {$path}: the handler does not render a Kpapy\\Resource" . ($error !== null ? " (it stopped with: {$error})" : '') . '.';

                continue;
            }

            if ($route['public']) {
                continue;
            }

            $catalogue = array_values(array_filter((array) ($definition['catalogue'] ?? []), 'is_string'));
            $missing   = $resolver->missing($definition['resource'], $catalogue);

            if ($missing !== []) {
                $problems[] = "API {$path}: {$definition['resource']} asks about operations the manifest does not declare: " . implode(', ', $missing) . '.';
            }
        }

        return $problems;
    }
}
