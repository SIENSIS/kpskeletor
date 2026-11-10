<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use CodeIgniter\Config\Services;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Filters;
use Config\Modules;
use Config\Routing;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Permissions\CatalogueBuilder;
use Modules\Core\Permissions\RouteSecurityAuditor;

/**
 * Default deny on routes (docs/specification.md, "RBAC"): every route under `backend/` runs the
 * `backend` chain and has a `can:` filter naming permissions of the catalogue; every route under
 * `api/v1/` has kpapy's filter (D-00-30). A route that breaks the rule fails the suite.
 *
 * @internal
 */
final class RoutesSecurityTest extends CIUnitTestCase
{
    public function testEveryApplicationRouteFollowsTheRules(): void
    {
        $routes = Services::routes(false)->loadRoutes();

        $this->assertSame([], (new RouteSecurityAuditor())->audit($routes, $this->catalogue()));
        $this->assertGreaterThan(0, count($this->backendRoutes($routes)), 'The back office has routes.');
    }

    public function testTheBackendChainCoversTheWholeBackOffice(): void
    {
        $filters = new Filters();

        $this->assertSame(['before' => ['backend', 'backend/*']], $filters->filters['backend'] ?? null);
        $this->assertArrayHasKey('backend', $filters->aliases);
    }

    public function testABackOfficeRouteWithoutCanFails(): void
    {
        $routes = $this->collection();
        $routes->get('backend/reports', 'Reports::index');
        $routes->post('backend/reports', 'Reports::save', ['filter' => 'reauth']);
        $routes->get('backend/stats', 'Stats::index', ['filter' => 'can:']);
        $routes->get('backend/ok', 'Ok::index', ['filter' => ['can:news-posts.list', 'reauth']]);
        $routes->get('backend', 'Home::index', ['filter' => 'can:all,news-posts.list,news-posts.edit']);
        $routes->get('backend/typo', 'Typo::index', ['filter' => 'can:news-post.list']);

        $this->assertSame([
            'GET backend/reports: no can: filter with a permission',
            'GET backend/stats: no can: filter with a permission',
            'GET backend/typo: can: names news-post.list, which is not in the permission catalogue',
            'POST backend/reports: no can: filter with a permission',
        ], $this->sorted((new RouteSecurityAuditor())->audit($routes, ['news-posts.list', 'news-posts.edit'])));
    }

    public function testApiRoutesNeedKpapy(): void
    {
        $routes = $this->collection();
        $routes->get('api/v1/posts', 'Posts::index', ['filter' => 'kpapy']);
        $routes->get('api/v1/feed', 'Feed::index', ['filter' => 'kpapy:optional']);
        $routes->post('api/v1/hooks', 'Hooks::receive', ['filter' => 'kpapy:hmac']);
        $routes->post('api/v1/auth/token', 'Token::create', ['filter' => 'kpapy:login']);
        $routes->post('api/v1/auth/token/verify', 'Token::verify', ['filter' => 'kpapy:login']);
        $routes->get('api/v1/events', 'Events::index', ['filter' => 'kpapy:public']);
        $routes->get('api/v1/open', 'Open::index');
        $routes->post('api/v1/login', 'Login::index', ['filter' => 'kpapy:login']);
        $routes->post('api/v1/comments', 'Comments::create', ['filter' => 'kpapy:public']);
        $routes->get('api/v1/odd', 'Odd::index', ['filter' => 'kpapy:anything']);
        // Outside api/v1/ there is no kpapy, CSRF or session filter: refused even with kpapy.
        $routes->post('api/internal/sync', 'Sync::run');
        $routes->get('api/v2/posts', 'Posts::index', ['filter' => 'kpapy']);

        $this->assertSame([
            'GET api/v1/odd: unknown kpapy mode "anything"',
            'GET api/v1/open: no kpapy filter',
            'GET api/v2/posts: API routes must live under api/v1/ (with the kpapy filter)',
            'POST api/internal/sync: API routes must live under api/v1/ (with the kpapy filter)',
            'POST api/v1/comments: kpapy:public is only for GET routes',
            'POST api/v1/login: kpapy:login is only for the auth/token* login routes',
        ], $this->sorted((new RouteSecurityAuditor())->audit($routes)));
    }

    /**
     * Sensitive actions (docs/specification.md, "Re-authentication") keep the `reauth` filter. The
     * actions that ask for it in the controller (`requireReauth()`: Users' 2FA reset and
     * impersonation, group changes) are covered by their own tests.
     */
    public function testSensitiveRoutesRequireReauthentication(): void
    {
        $routes = Services::routes(false)->loadRoutes();

        foreach ([
            ['POST', 'backend/profile/two-factor/email'],
            ['GET', 'backend/profile/two-factor/app'],
            ['POST', 'backend/profile/two-factor/app'],
            ['POST', 'backend/profile/two-factor/disable'],
            ['POST', 'backend/profile/two-factor/recovery-codes'],
            ['POST', 'backend/profile/api-tokens'],
            ['GET', 'backend/settings/security'],
            ['POST', 'backend/settings/security'],
            ['POST', 'backend/settings/actions/assets'],
            ['GET', 'backend/users/access/([0-9]+)'],
            ['POST', 'backend/users/access/([0-9]+)'],
            ['POST', 'backend/users/permissions'],
        ] as [$verb, $path]) {
            $this->assertArrayHasKey($path, $routes->getRoutes($verb), "{$verb} {$path} exists.");
            $this->assertContains('reauth', $routes->getFiltersForRoute($path, $verb), "{$verb} {$path} asks for re-authentication.");
        }
    }

    /**
     * @return list<string>
     */
    private function catalogue(): array
    {
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        return array_keys((new CatalogueBuilder())->build($manifests));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function backendRoutes(RouteCollection $routes): array
    {
        return array_values(array_filter((new RouteSecurityAuditor())->routes($routes), static fn (array $r): bool => str_starts_with($r[1], 'backend')));
    }

    private function collection(): RouteCollection
    {
        $modules            = new Modules();
        $modules->discoverInComposer = false;
        $modules->enabled            = false;

        return new RouteCollection(Services::locator(), $modules, new Routing());
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function sorted(array $lines): array
    {
        sort($lines);

        return $lines;
    }
}
