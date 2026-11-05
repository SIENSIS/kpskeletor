<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\Exceptions\ConfigException;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Kpapy;
use Config\Kpcrudy;
use Config\Services;
use Kpcrudy\Crud;
use Modules\Core\Crud\CrudFactory;
use Modules\Core\Crud\CrudInspection;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Security\MaskKey;

/**
 * kpcrudy integration (session 09): the library's configuration, the shared mask key, the
 * factory's routes and the theme pieces CRUDs use (page header, toasts).
 *
 * @internal
 */
final class KpcrudyIntegrationTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('theme')->reset();
    }

    protected function tearDown(): void
    {
        CrudFactory::inspected();
        Factories::reset('config');
        parent::tearDown();
    }

    public function testTheConfigurationFollowsTheSpecification(): void
    {
        $config = config(Kpcrudy::class);

        $this->assertSame('bootstrap5', $config->theme);
        $this->assertTrue($config->maskIds);
        $this->assertSame(CrudPermissions::class, $config->permissionResolver);
        $this->assertTrue($config->denyByDefault);
        $this->assertFalse($config->showTitle);
        $this->assertSame('content', $config->layoutSection);
        $this->assertSame(theme_layout('backend'), $config->layout);
        $this->assertSame(['success' => 'success', 'danger' => 'error', 'warning' => 'warning', 'info' => 'info'], $config->flashKeys);
        $this->assertTrue($config->csrfProtection);
    }

    public function testAssetsAreLocalAndUploadsNeverPublic(): void
    {
        $config = config(Kpcrudy::class);

        foreach ([$config->bootstrapCss, $config->bootstrapJs, $config->iconsCss] as $url) {
            $this->assertStringStartsWith(rtrim(config(App::class)->baseURL, '/') . '/assets/vendor/', $url);
            $this->assertFileExists(FCPATH . substr($url, strlen(rtrim(config(App::class)->baseURL, '/')) + 1));
        }

        $this->assertStringStartsWith(WRITEPATH, $config->uploadPath, 'Not under public/: kpcrudy upload fields are never used.');
        $this->assertStringStartsNotWith(FCPATH, $config->uploadPath);
    }

    public function testBothLibrariesShareAppMaskKey(): void
    {
        $key = config(App::class)->maskKey;

        $this->assertGreaterThanOrEqual(MaskKey::MIN_LENGTH, strlen($key), 'phpunit.xml.dist sets app.maskKey.');
        $this->assertSame($key, config(Kpcrudy::class)->maskKey);
        $this->assertSame($key, config(Kpapy::class)->maskKey);
        $this->assertSame(Crud::maskId(5, 'news_posts'), Crud::maskId(5, 'news_posts'), 'Stable codes.');
        $this->assertNotSame('5', Crud::maskId(5, 'news_posts'));
    }

    public function testProductionRefusesToBootWithoutAMaskKey(): void
    {
        $this->assertNull(MaskKey::problem('', 'development'), 'Allowed outside production.');
        $this->assertNull(MaskKey::problem('', 'testing'));
        $this->assertNotNull(MaskKey::problem('', 'production'));
        $this->assertNotNull(MaskKey::problem('too-short', 'development'), 'Never shorter than 32 characters.');
        $this->assertNull(MaskKey::problem(MaskKey::generate(), 'production'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', MaskKey::generate());

        MaskKey::assertBootable(MaskKey::generate(), 'production');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('app.maskKey');
        MaskKey::assertBootable('', 'production');
    }

    public function testTheEnvTemplateDocumentsTheKeyWithoutAValue(): void
    {
        $env = (string) file_get_contents(ROOTPATH . 'env');

        $this->assertMatchesRegularExpression('/^# app\.maskKey =\s*$/m', $env);
    }

    public function testFactoryRoutesAddTheBackendChainCanAndTheName(): void
    {
        $routes = Services::routes(false);
        CrudFactory::routes($routes, 'news-posts', 'backend/news/posts', '\Modules\News\Controllers\Posts::index', ['filter' => 'reauth']);

        $this->assertSame(['backend', 'can:news-posts.list', 'reauth'], $routes->getFiltersForRoute('backend/news/posts', 'GET'));
        $this->assertSame(['backend', 'can:news-posts.list', 'reauth'], $routes->getFiltersForRoute('backend/news/posts/(.*)', 'POST'));
        $this->assertSame('/backend/news/posts', $routes->reverseRoute('news-posts'));
        $this->assertTrue($routes->getRoutesOptions('backend/news/posts', 'GET')['kpcrudy']);

        $this->expectException(\InvalidArgumentException::class);
        CrudFactory::routes($routes, 'news posts', 'backend/x', 'X::index');
    }

    public function testFactoryCrudsUseTheResourceAndTheBackendLayout(): void
    {
        $crud = CrudFactory::make('news-posts');

        $this->assertSame('news-posts', $crud->getResource());
        $this->assertFalse(CrudFactory::inspecting());

        CrudFactory::inspect();
        $made = CrudFactory::make('news-categories');
        $this->assertSame([$made], CrudFactory::inspected());
        $this->assertFalse(CrudFactory::inspecting());
        $this->assertSame('news-categories', (new CrudInspection($made))->crud->getResource());
    }

    public function testPageTopBuildsAHeaderFromTheCrudTitle(): void
    {
        $html = view(theme_resolve('partials/page_top'), [
            'pageHeader'  => null, // view data outlives view() calls
            'title'       => 'Posts <x>',
            'breadcrumbs' => [['label' => 'Back office', 'url' => '/backend'], ['label' => 'News', 'url' => null]],
        ]);

        $this->assertStringContainsString('page-header', $html);
        $this->assertStringContainsString('Posts &lt;x&gt;', $html);
        $this->assertStringContainsString('aria-current="page"', $html);

        $crumbs = view(theme_resolve('partials/page_top'), ['pageHeader' => null, 'title' => null, 'breadcrumbs' => [['label' => 'News', 'url' => null]]]);
        $this->assertStringNotContainsString('page-header', $crumbs, 'No title: breadcrumbs only.');
    }

    public function testFlashMessagesBecomeToastsWithAnAlertFallback(): void
    {
        $_SESSION = [];
        session()->setFlashdata('success', 'Saved <b>');
        session()->setFlashdata('info', 'Note');
        session()->setFlashdata('error', 'Refused');

        $html = view(theme_resolve('partials/flash'));

        $this->assertStringContainsString('data-component="core/ui/flash"', $html);
        $this->assertStringContainsString('data-flash="success"', $html);
        $this->assertStringContainsString('data-flash="info"', $html);
        $this->assertStringContainsString('data-flash="danger"', $html);
        $this->assertStringContainsString('Saved &lt;b&gt;', $html);
        $this->assertFileExists(ROOTPATH . 'modules/Core/Assets/js/ui/flash.js');

        $_SESSION = [];
        $this->assertStringNotContainsString('data-component', view(theme_resolve('partials/flash')));
    }

    public function testKpcrudyConfirmationsUseTheThemeModal(): void
    {
        $confirm = (string) file_get_contents(ROOTPATH . 'modules/Core/Assets/js/ui/confirm.js');
        $app     = (string) file_get_contents(ROOTPATH . 'modules/Core/Assets/js/app.js');

        $this->assertStringContainsString("addEventListener('kpcrudy:confirm'", $confirm);
        $this->assertStringContainsString('event.preventDefault()', $confirm);
        $this->assertStringContainsString('detail.proceed()', $confirm);
        $this->assertStringContainsString('bindKpcrudyConfirmations(document)', $app);
    }
}
