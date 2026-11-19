<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests;

use CodeIgniter\Config\Factories;
use Config\Auth;
use Config\Filters;
use Config\Services;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Permissions\RouteSecurityAuditor;
use Modules\Core\Widgets\QuickLinks;
use Modules\Core\Widgets\WidgetOptions;

/**
 * A site installed with `app.backendPath = admin` (D-40-01): the whole back office answers under
 * `/admin`, `/backend/...` answers 404, and stored links still work (D-40-02).
 *
 * @internal
 */
final class BackendPathSiteTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::usePrefix('admin');
    }

    protected function tearDown(): void
    {
        self::usePrefix('backend');
        parent::tearDown();
    }

    /**
     * The configuration as `.env` would set it at boot: App, the configs built from it (Filters,
     * Auth) and the routes.
     */
    private static function usePrefix(string $prefix): void
    {
        config('App')->backendPath = $prefix;
        Factories::injectMock('config', 'Filters', new Filters());
        Factories::injectMock('config', 'Auth', new Auth());
        Services::resetSingle('routes');
        Services::resetSingle('router');
        Services::resetSingle('filters');
    }

    public function testTheBackOfficeAnswersUnderThePrefix(): void
    {
        $this->send('GET', 'admin')->assertRedirectTo(site_url('login'));

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com')->assertRedirectTo(site_url('admin'));

        $this->send('GET', 'admin')->assertOK();
        $this->send('GET', 'admin/profile')->assertOK();
        $this->assertNotFound('GET', 'backend');
        $this->assertNotFound('GET', 'backend/profile');
    }

    public function testRoutesKeepTheirFilters(): void
    {
        $routes = Services::routes(false)->loadRoutes();

        $this->assertSame([], (new RouteSecurityAuditor())->audit($routes));
        $this->assertContains('can:dashboard.view', $routes->getFiltersForRoute('admin', 'GET'));
        $this->assertContains('can:users-users.list', $routes->getFiltersForRoute('admin/users', 'GET'));
        $this->assertSame([], $routes->getFiltersForRoute('backend/users', 'GET'));
        $this->assertSame(['before' => ['admin', 'admin/*']], config('Filters')->filters['backend']);
    }

    public function testAForcedPasswordChangeStaysInsideThePrefix(): void
    {
        $user = $this->createUser();
        $user->forcePasswordReset();
        $this->login('ana@example.com');

        $this->send('GET', 'admin/profile')->assertRedirectTo(site_url('admin/profile/password'));
        $this->send('GET', 'admin/profile/password')->assertSee(lang('Auth.msg.mustChangePassword'));
    }

    public function testCrudUrlsAndTheMenuUseThePrefix(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $response = $this->send('GET', 'admin/users');
        $response->assertOK();
        $this->assertStringContainsString('admin/users', (string) $response->getBody());
        $this->assertStringNotContainsString('/backend/users', (string) $response->getBody());

        $user = model(\CodeIgniter\Shield\Models\UserModel::class)->findByCredentials(['email' => 'boss@example.com']);
        \assert($user !== null);
        $urls = [];

        foreach ((new MenuBuilder())->tree($user) as $entries) {
            foreach ($entries as $entry) {
                if ($entry->url() !== null) {
                    $urls[] = (string) $entry->url();
                }
            }
        }

        $this->assertNotSame([], $urls);

        foreach ($urls as $url) {
            $this->assertStringNotContainsString('/backend', $url);
        }

        $this->assertContains(site_url('admin'), $urls);
    }

    public function testQuickLinksAreStoredCanonicalAndShownUnderThePrefix(): void
    {
        $result = (new WidgetOptions())->validate(new QuickLinks(), ['links' => [
            ['ref' => 'url', 'label' => 'Users', 'url' => '/admin/users', 'position' => '1'],
            ['ref' => 'url', 'label' => 'Old', 'url' => '/backend/news', 'position' => '2'],
        ]]);
        $stored = ['links' => $result['values']['links'] ?? null];

        $this->assertSame([], $result['errors']);
        $this->assertSame([['label' => 'Users', 'url' => '/backend/users'], ['label' => 'Old', 'url' => '/backend/news']], $stored['links']);

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $html = html_entity_decode((new QuickLinks())->render(['links' => $stored['links']]), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString(site_url('admin/users'), $html);
        $this->assertStringContainsString(site_url('admin/news'), $html);
        $this->assertStringNotContainsString('/backend/', $html);
    }
}
