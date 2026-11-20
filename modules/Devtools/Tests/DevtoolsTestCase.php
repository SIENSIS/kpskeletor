<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Shield\Entities\User;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Devtools\Routing\DevtoolsRoutes;

/**
 * Base of the Devtools feature tests. The suite runs with `ENVIRONMENT === 'testing'`, so the
 * module's `Config/Routes.php` registers nothing (lock 1); `withDevtoolsRoutes()` adds the routes
 * as development would, and `fromLocalhost()` / `fromNetwork()` set the client and the `Host`.
 *
 * @internal
 */
abstract class DevtoolsTestCase extends AuthTestCase
{
    /**
     * The routes added by a test must not reach the next test class (a menu built later would
     * show the Devtools entry).
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Services::resetSingle('routes');
        Services::resetSingle('router');
    }

    protected function withDevtoolsRoutes(): void
    {
        DevtoolsRoutes::define(service('routes')->loadRoutes());
    }

    protected function fromLocalhost(string $host = 'localhost:8080'): void
    {
        service('superglobals')->setServer('REMOTE_ADDR', '127.0.0.1');
        service('superglobals')->setServer('HTTP_HOST', $host);
    }

    protected function fromNetwork(string $address = '192.0.2.10', string $host = 'localhost:8080'): void
    {
        service('superglobals')->setServer('REMOTE_ADDR', $address);
        service('superglobals')->setServer('HTTP_HOST', $host);
    }

    protected function loginSuperadmin(): User
    {
        $user = $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        return $user;
    }
}
