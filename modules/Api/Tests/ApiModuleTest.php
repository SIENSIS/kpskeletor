<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Kpapy as KpapyConfig;
use Config\Kpcrudy as KpcrudyConfig;
use Config\Services;
use Kpapy\Auth\TwoFactor\WebCodeProvider;
use Modules\Api\Auth\TokenAuthenticator;
use Modules\Api\Config\Module;
use Modules\Api\Database\Migrations\CreateApiTokenIps;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\RouteSecurityAuditor;
use Modules\Core\Registry\ApiRouteChecker;

/**
 * The Api module's manifest and migration, the kpapy configuration the Core shares with kpcrudy,
 * and the filters of every real `api/v1/` route.
 *
 * @internal
 */
final class ApiModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testManifestIsValid(): void
    {
        $errors = (new ManifestValidator())->validate(new Module(), dirname(__DIR__));

        $this->assertSame([], $errors);
        $this->assertContains('Api', ModuleToggler::CORE_MODULES, 'The API core cannot be disabled.');
    }

    public function testMigrationUpDownUp(): void
    {
        $this->assertTrue($this->db->tableExists('api_token_ips'));

        $migration = new CreateApiTokenIps(\Config\Database::forge('tests'));
        $migration->down();
        $this->assertFalse($this->db->tableExists('api_token_ips'));
        $migration->up();
        $this->assertTrue($this->db->tableExists('api_token_ips'));
    }

    public function testKpapySharesTheResolverAndTheMaskKeyWithKpcrudy(): void
    {
        $api  = new KpapyConfig();
        $crud = new KpcrudyConfig();

        $this->assertSame(CrudPermissions::class, $api->permissionResolver);
        $this->assertSame($crud->permissionResolver, $api->permissionResolver);
        $this->assertTrue($api->denyByDefault);
        $this->assertTrue($api->checkScopes);
        $this->assertTrue($api->maskIds);
        $this->assertNotSame('', $api->maskKey);
        $this->assertSame($crud->maskKey, $api->maskKey);
        $this->assertSame(TokenAuthenticator::class, $api->authenticator);
    }

    public function testSecureDefaults(): void
    {
        $config = new KpapyConfig();

        $this->assertSame([], $config->corsOrigins, 'CORS closed by default.');
        $this->assertTrue($config->requireHttps);
        $this->assertFalse($config->requireIfMatch, 'If-Match supported, not required.');
        $this->assertSame(['totp', 'email'], $config->twoFactorMethods, "kpapy's web method is not used.");
        $this->assertNotContains(WebCodeProvider::class, $config->twoFactorProviders);
        $this->assertStringStartsWith(WRITEPATH, $config->uploadPath, 'Uploads never in public/.');
        $this->assertStringNotContainsString('public', $config->uploadPath);
        $this->assertSame(30 * DAY, $config->tokenLifetime, 'Api.appTokenDays');
        $this->assertSame(120, $config->rateLimit, 'Core.rateLimitApiToken');
        $this->assertSame(60, $config->rateLimitPublic, 'Core.rateLimitApiIp');
    }

    public function testRateLimitsAndTokenLifetimeFollowTheSettings(): void
    {
        setting('Core.rateLimitApiToken', 300);
        setting('Core.rateLimitApiIp', 20);
        setting('Api.appTokenDays', 7);

        $config = new KpapyConfig();
        $this->assertSame(300, $config->rateLimit);
        $this->assertSame(20, $config->rateLimitPublic);
        $this->assertSame(7 * DAY, $config->tokenLifetime);
    }

    public function testEveryApiRouteOfTheApplicationHasKpapyAndTheLoginOnlyOnAuthToken(): void
    {
        $routes   = Services::routes(false)->loadRoutes();
        $auditor  = new RouteSecurityAuditor();
        $problems = $auditor->audit($routes);
        $this->assertSame([], $problems);

        $login = [];

        foreach ($auditor->routes($routes) as [$verb, $from]) {
            if (in_array('kpapy:login', $routes->getFiltersForRoute($from, $verb), true)) {
                $login[] = $verb . ' ' . $from;
            }
        }

        sort($login);
        $this->assertSame(['POST api/v1/auth/token', 'POST api/v1/auth/token/verify'], $login);
        $this->assertContains('kpapy', $routes->getFiltersForRoute('api/v1/me', 'GET'));
        $this->assertContains('can:api.docs', $routes->getFiltersForRoute('backend/api/docs', 'GET'));
        $this->assertContains('reauth', $routes->getFiltersForRoute('backend/profile/api-tokens', 'POST'));
    }

    public function testApiManifestEntriesMustMatchARoute(): void
    {
        $this->assertSame([], (new ApiRouteChecker())->check(['Api' => new Module()]));

        $broken = new class () extends ModuleManifest {
            public string $name = 'Ghost';
            public array $api   = ['posts' => ['path' => 'ghost/posts', 'handler' => '\Modules\Ghost\Controllers\Api\Posts::index']];
        };
        $routes = new RouteCollection(service('locator'), new \Config\Modules(), config('Routing'));

        $this->assertCount(1, (new ApiRouteChecker())->check(['Ghost' => $broken], $routes));
    }
}
