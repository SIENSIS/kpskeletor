<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\App;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Frontend\AccountLink;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The account link of the public navbar (theme option `showAccountLink`): "Sign in" for guests,
 * "Administration" for users who can open the back office, "My account" for the rest, nothing
 * when the option is off.
 *
 * @internal
 */
final class AccountLinkTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(App::class)->defaultLocale = 'ca';
        service('routes')->loadRoutes();

        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CacheInvalidator())->invalidate(['menus', 'modules']);
        service('theme')->reset();
    }

    protected function tearDown(): void
    {
        Services::resetSingle('settings');
        Services::resetSingle('language');
        parent::tearDown();
    }

    public function testGuestsSeeSignIn(): void
    {
        $link = (new AccountLink())->for(null);

        $this->assertSame('login', $link['kind'] ?? null);
        $this->assertSame(url_to('login'), $link['url']);

        $html = $this->html('/');
        $this->assertMatchesRegularExpression('#<a class="nav-link[^"]*" href="' . preg_quote(url_to('login'), '#') . '" data-account-link="login">.*Entra</span></a>#', $html);
        $this->assertMatchesRegularExpression('#id="site-menu".*site-account-link#s', $html, 'Inside the collapsible menu.');
    }

    public function testBackOfficeUsersSeeAdministration(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $link = (new AccountLink())->for($user);

        $this->assertSame('backend', $link['kind'] ?? null);
        $this->assertSame(url_to('dashboard'), $link['url'], 'The first entry of the back-office menu.');

        $this->login('ana@example.com');
        $html = $this->html('/');
        $this->assertStringContainsString('data-account-link="backend"', $html);
        $this->assertStringContainsString('Administració', $html);
        $this->assertStringNotContainsString('data-account-link="login"', $html);
    }

    public function testUsersWithoutTheBackOfficeSeeTheirAccount(): void
    {
        (new GrantManager())->setGroupGrants(null, 'user', array_values(array_filter(
            (new PermissionCatalogue())->grants('user'),
            static fn (string $p): bool => ! str_starts_with($p, 'dashboard.') && ! str_starts_with($p, 'news-') && ! str_starts_with($p, 'events-') && ! str_starts_with($p, 'tasks-') && ! str_starts_with($p, 'files-') && ! str_starts_with($p, 'pages'),
        )));
        $user = $this->createUser('ana@example.com', 'user');
        $tree = (new \Modules\Core\Menus\MenuBuilder())->tree($user);
        $this->assertSame([], ($tree['sidebar'] ?? []) + ($tree['settings'] ?? []), 'This user has no back-office screen.');

        $link = (new AccountLink())->for($user);
        $this->assertSame('account', $link['kind'] ?? null);
        $this->assertSame(url_to('auth-profile'), $link['url']);

        $this->login('ana@example.com');
        $html = $this->html('/');
        $this->assertStringContainsString('data-account-link="account"', $html);
        $this->assertStringNotContainsString('Administració', $html);
    }

    public function testTheOptionOffHidesIt(): void
    {
        service('settings')->set('Theme.showAccountLink', false, 'theme:default');
        service('theme')->reset();

        $this->assertStringNotContainsString('site-account-link', $this->html('/'));
    }

    private function html(string $path): string
    {
        return html_entity_decode((string) $this->send('GET', $path)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
