<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleToggler;
use Modules\Users\Config\Module;

/**
 * The manifest: valid, a core module, and impersonation never granted by default.
 *
 * @internal
 */
final class UsersModuleTest extends CIUnitTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testItIsACoreModule(): void
    {
        $this->assertContains('Users', ModuleToggler::CORE_MODULES);
    }

    public function testNoGroupGetsImpersonationByDefault(): void
    {
        foreach ((new Module())->defaultGrants as $group => $grants) {
            $this->assertNotContains('users-users.impersonate', $grants, $group);
            $this->assertNotContains('users-users.*', $grants, $group);
        }
    }

    public function testTheImpersonationLimitIsASecuritySetting(): void
    {
        $setting = (new Module())->settings['impersonationMinutes'];

        $this->assertSame(30, $setting['default']);
        $this->assertStringStartsWith('security', $setting['group']);
    }
}
