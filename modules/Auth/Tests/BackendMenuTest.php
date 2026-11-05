<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The generated menu in the real back-office layout: the user menu comes from the `user` zone
 * (Auth's profile and sessions), filtered by permission, with the current page marked; the
 * breadcrumbs of a screen without its own come from the menu.
 *
 * @internal
 */
final class BackendMenuTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CoreCache())->invalidate('menus');
        (new CoreCache())->invalidate('modules');
    }

    public function testUserMenuIsGeneratedAndMarksTheCurrentPage(): void
    {
        $this->createUser();
        $this->login('ana@example.com');

        $html = (string) $this->send('GET', 'backend/profile/sessions')->getBody();

        $this->assertMatchesRegularExpression('#<a class="dropdown-item" href="[^"]*backend/profile">.*My profile</a>#', $html);
        $this->assertMatchesRegularExpression('#<a class="dropdown-item active" href="[^"]*backend/profile/sessions" aria-current="page">.*My sessions</a>#', $html);
    }

    public function testEntriesWithoutPermissionAreNotShown(): void
    {
        $this->createUser();
        $this->login('ana@example.com');

        // The group loses "my sessions": the entry disappears from the user menu.
        (new \Modules\Core\Permissions\GrantManager())->setGroupGrants(null, 'user', ['auth-profile.view', 'auth-profile.edit']);

        $html = (string) $this->send('GET', 'backend/profile')->getBody();

        $this->assertMatchesRegularExpression('#class="dropdown-item active" href="[^"]*backend/profile" aria-current="page">#', $html);
        $this->assertDoesNotMatchRegularExpression('#class="dropdown-item[^"]*" href="[^"]*backend/profile/sessions"#', $html);
    }
}
