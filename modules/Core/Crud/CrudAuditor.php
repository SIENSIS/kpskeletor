<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Router\RouteCollection;
use Config\Services;
use Kpcrudy\Crud;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\RouteSecurityAuditor;

/**
 * Compares every kpcrudy CRUD of the routes with the permission catalogue
 * (`php spark permissions:check`, D-07-05):
 *
 * - every kpcrudy route (`Crud::routes()` marks them) is made with `CrudFactory::routes()`: it is
 *   named after its resource and has `can:<resource>.list`;
 * - its controller builds the CRUD with `CrudFactory::make()` / `BackendController::crud()` for
 *   that same resource;
 * - every operation the CRUD can ask about (`permissionCatalogue()`, custom operations and the
 *   `permission` of actions included) is declared in the catalogue.
 *
 * The controller action runs with `CrudFactory::inspect()` on: `renderCrud()` stops before
 * rendering and hands the configured CRUD back. It runs without a user, so operations a CRUD
 * only adds for some users are not seen here; `CrudPermissions` still fails loudly outside
 * production the first time such a CRUD asks about them.
 */
final class CrudAuditor
{
    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * @return list<string> One line per problem: "GET <route>: <problem>"
     */
    public function audit(RouteCollection $routes): array
    {
        $problems = [];

        foreach ($this->crudRoutes($routes) as $route) {
            foreach ($this->routeProblems($route) as $problem) {
                $problems[] = 'GET ' . $route['path'] . ': ' . $problem;
            }
        }

        return $problems;
    }

    /**
     * The base route of every kpcrudy CRUD: path, handler (without the operation argument),
     * route name and filters.
     *
     * @return list<array{path: string, handler: string, as: string|null, filters: list<string>}>
     */
    public function crudRoutes(RouteCollection $routes): array
    {
        $found = [];
        $all   = $routes->getRoutes('GET', false);

        foreach ($all as $from => $handler) {
            $from    = (string) $from;
            $options = $routes->getRoutesOptions($from, 'GET');

            if (empty($options['kpcrudy']) || ! is_string($handler)) {
                continue;
            }

            // The "<path>/(:any)" route of a CRUD whose base route is listed too.
            if (str_ends_with($from, '/(.*)') && isset($all[substr($from, 0, -5)])) {
                continue;
            }

            $found[] = [
                'path'    => ltrim($from, '/'),
                'handler' => (string) preg_replace('#/\$\d+$#', '', $handler),
                'as'      => is_string($options['as'] ?? null) ? $options['as'] : null,
                'filters' => $routes->getFiltersForRoute($from, 'GET'),
            ];
        }

        return $found;
    }

    /**
     * @param array{path: string, handler: string, as: string|null, filters: list<string>} $route
     *
     * @return list<string>
     */
    private function routeProblems(array $route): array
    {
        $resource = $route['as'];

        if ($resource === null || ! in_array($resource . '.list', RouteSecurityAuditor::canPermissions($route['filters']) ?? [], true)) {
            return ['register the CRUD with CrudFactory::routes() (route name = resource, can:<resource>.list).'];
        }

        [$crud, $error] = $this->inspect($route['handler']);

        if ($crud === null) {
            return [(string) $error];
        }

        if ($crud->getResource() !== $resource) {
            return ["the controller builds the CRUD of {$crud->getResource()}, but the route is {$resource}."];
        }

        $missing = CrudPermissions::forUser(null, $this->catalogue)->undeclared($resource, $crud);

        return $missing === []
            ? []
            : ["{$resource} asks about operations the manifest does not declare: " . implode(', ', $missing) . '.'];
    }

    /**
     * Runs a CRUD action with inspection on.
     *
     * @return array{0: Crud|null, 1: string|null}
     */
    private function inspect(string $handler): array
    {
        [$class, $method] = array_pad(explode('::', ltrim($handler, '\\'), 2), 2, 'index');

        if (! class_exists($class) || ! is_subclass_of($class, Controller::class) || ! method_exists($class, $method)) {
            return [null, "the handler {$handler} does not exist."];
        }

        $error    = null;
        $previous = service('request');
        // Always a GET (the list screen), whatever the previous request was (a POST in tests).
        $request  = ($previous instanceof IncomingRequest ? $previous : Services::incomingrequest(null, false))->withMethod('GET');
        Services::injectMock('request', $request); // kpcrudy needs a web request (spark has a CLI one)
        CrudFactory::inspect();

        try {
            $controller = new $class();
            $controller->initController($request, service('response'), service('logger'));
            $controller->{$method}();
        } catch (CrudInspection) {
            // renderCrud() reached: the CRUD is recorded.
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            $cruds = CrudFactory::inspected();
            Services::injectMock('request', $previous);
        }

        if ($cruds === []) {
            return [null, 'the controller does not build its CRUD with CrudFactory::make() / $this->crud() and render it with $this->renderCrud()'
                . ($error !== null ? " (it stopped with: {$error})" : '') . '.'];
        }

        return [$cruds[array_key_last($cruds)], null];
    }
}
