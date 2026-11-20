<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\ManifestValidator;
use Modules\Devtools\Config\Module;
use Modules\Devtools\Routing\DevtoolsRoutes;

/**
 * The locks of the Devtools module that live in the module (docs/devtools.md): routes only in
 * development, loopback client and local `Host`, the permissions, re-authentication and the rate
 * limit on writes. The Core's locks (`$developmentOnly` in sync, enable, install, the Modules
 * screen and `security:check`) are in `Modules\Core\Tests\DevelopmentOnlyTest`.
 *
 * @internal
 */
final class DevtoolsLocksTest extends DevtoolsTestCase
{
    public function testManifestIsValidAndDevelopmentOnly(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertTrue($manifest->developmentOnly);
        $this->assertFalse($manifest->enabledByDefault);
        $this->assertSame([], $manifest->defaultGrants);
        $this->assertSame('devtools', $manifest->permissionPrefix('tools'));
    }

    public function testNoRouteOutsideDevelopment(): void
    {
        // The suite runs in `testing`: Config/Routes.php registered nothing.
        $this->assertNotSame('development', ENVIRONMENT);
        $this->loginSuperadmin();
        $this->fromLocalhost();

        foreach (['devtools', 'devtools/recipes', 'devtools/docs/cruds'] as $path) {
            $this->assertNotFound('GET', backend_path($path));
        }

        foreach (service('routes')->loadRoutes()->getRoutes('GET') + service('routes')->getRoutes('POST') as $from => $to) {
            $this->assertStringNotContainsString('Modules\Devtools', is_string($to) ? $to : '', (string) $from);
        }
    }

    public function testSuperadminFromLocalhostSeesTheScreens(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $home = $this->send('GET', backend_path('devtools'));
        $home->assertOK();
        $home->assertSee(lang('Devtools.banner'));
        $home->assertSee(esc(lang('Devtools.recipes.title')));
    }

    public function testLoopbackAddressesAndHostsAreAccepted(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();

        foreach (['127.0.0.1' => '127.0.0.1:8080', '::1' => '[::1]:8080', '127.0.0.5' => 'localhost'] as $address => $host) {
            $this->fromNetwork($address, $host);
            $this->send('GET', backend_path('devtools'))->assertOK();
        }
    }

    public function testRequestsFromTheNetworkAnswer404(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();

        // Another client, even with a local Host.
        $this->fromNetwork('192.0.2.10', 'localhost:8080');
        $this->assertNotFound('GET', backend_path('devtools'));
        $this->assertNotFound('GET', backend_path('devtools/recipes'));

        // Proxy headers are not trusted.
        $this->fromNetwork('192.0.2.10', 'localhost');
        service('superglobals')->setServer('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        $this->assertNotFound('GET', backend_path('devtools'));
    }

    public function testAForeignHostAnswers404(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();

        foreach (['dev.example.com', 'example.com:8080', '192.168.1.20:8080', 'localhost.example.com', ''] as $host) {
            $this->fromNetwork('127.0.0.1', $host);
            $this->assertNotFound('GET', backend_path('devtools'));
        }
    }

    public function testADisabledModuleAnswers404(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();
        $this->fromLocalhost();
        db_connect()->table('modules')->where('name', 'Devtools')->update(['enabled' => 0]);
        (new CoreCache())->invalidate('modules');

        $this->assertNotFound('GET', backend_path('devtools/recipes'));
    }

    public function testTheScreensNeedDevtoolsView(): void
    {
        $this->withDevtoolsRoutes();
        $this->fromLocalhost();

        $this->send('GET', backend_path('devtools/recipes'))->assertRedirectTo(site_url('login'));

        // Nobody is granted Devtools: an administrator is refused.
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->fromLocalhost();
        $this->send('GET', backend_path('devtools'))->assertStatus(403);
        $this->send('GET', backend_path('devtools/recipes'))->assertStatus(403);
    }

    public function testEveryDevtoolsRouteHasItsLocks(): void
    {
        $routes = service('routes')->loadRoutes();
        DevtoolsRoutes::define($routes);
        $prefix = backend_path('devtools');
        $found  = 0;

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            foreach (array_keys($routes->getRoutes($verb)) as $from) {
                if ($from !== $prefix && ! str_starts_with((string) $from, $prefix . '/')) {
                    continue;
                }

                $filters = $routes->getFiltersForRoute((string) $from, $verb);
                $draft   = in_array(substr((string) $from, strlen($prefix) + 1), DevtoolsRoutes::DRAFT_ROUTES, true);
                $expect  = $verb === 'GET' ? DevtoolsRoutes::VIEW : ($draft && $verb === 'POST' ? DevtoolsRoutes::DRAFT : DevtoolsRoutes::WRITE);
                $this->assertSame($expect, $filters, "{$verb} {$from}");
                $found++;
            }
        }

        $this->assertGreaterThanOrEqual(3, $found);
        $this->assertSame(['devtools', 'can:devtools.write', 'reauth', 'ratelimit:forms'], DevtoolsRoutes::WRITE);
        $this->assertSame(['devtools', 'can:devtools.view'], DevtoolsRoutes::DRAFT);
        $this->assertSame(['generator', 'generator/load'], DevtoolsRoutes::DRAFT_ROUTES, 'Only the generator\'s draft routes skip WRITE (D-42-02).');
    }

    public function testAWriteRouteNeedsDevtoolsWriteAndReauthentication(): void
    {
        $this->withDevtoolsRoutes();
        // A write route as sessions 42–43 add them (DevtoolsRoutes::WRITE).
        service('routes')->post(backend_path('devtools/probe'), '\Modules\Devtools\Controllers\Home::index', ['filter' => DevtoolsRoutes::WRITE]);

        $this->loginWith('devtools.view');
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/probe'))->assertStatus(403);

        // From the network: 404 before anything else.
        $this->fromNetwork();
        $this->assertNotFound('POST', backend_path('devtools/probe'));
    }

    public function testAWriteRouteAsksForThePasswordAgain(): void
    {
        $this->withDevtoolsRoutes();
        service('routes')->post(backend_path('devtools/probe'), '\Modules\Devtools\Controllers\Home::index', ['filter' => DevtoolsRoutes::WRITE]);

        // The permission alone is not enough: the password is asked again.
        $this->loginWith('devtools.view', 'devtools.write');
        $this->fromLocalhost();
        // A password login counts as re-authentication (D-06-04) until the window ends.
        Time::setTestNow(Time::now()->addMinutes(16));
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/probe'))->assertRedirectTo(site_url('auth/reauth'));
        Time::setTestNow();

    }

    public function testViewsHaveNoInlineCode(): void
    {
        foreach ([...(glob(dirname(__DIR__) . '/Views/*.php') ?: []), ...(glob(dirname(__DIR__) . '/Views/*/*.php') ?: [])] as $file) {
            $code = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/<script|<style|\sstyle=|\son[a-z]+=/i', $code, basename($file));
        }
    }
}
