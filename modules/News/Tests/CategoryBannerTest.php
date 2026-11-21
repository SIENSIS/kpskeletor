<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Tests\StorageFixturesTrait;

/**
 * Session 46: the category banner (stored through the file picker, forged keys refused, shown as
 * a hero on the category page and its articles) and the main news list page (D-46-03: `/news`
 * redirects to it only when it is set and published).
 *
 * @internal
 */
final class CategoryBannerTest extends NewsTestCase
{
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeStorage();
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
    }

    protected function tearDown(): void
    {
        service('settings')->forget('News.listPage');
        $this->removeStorageRoot();
        parent::tearDown();
    }

    private function category(?string $banner): int
    {
        db_connect()->table('news_categories')->insert(['slug' => 'sortides', 'position' => 1, 'active' => 1, 'banner' => $banner]);
        $id = (int) db_connect()->insertID();
        db_connect()->table('news_category_translations')->insert(['category_id' => $id, 'locale' => 'ca', 'name' => 'Sortides']);

        return $id;
    }

    public function testTheBannerIsShownOnTheCategoryAndItsArticles(): void
    {
        $file     = service('storage')->storeContents(self::png(40, 10), 'banner.png', 'public', 'modules/news');
        $category = $this->category($file->key);
        $this->newsPost('Sortida al bosc', ['visibility' => 'public', 'category_id' => $category, 'slug' => 'sortida-al-bosc']);
        $url = service('storage')->url($file);

        $list = $this->body('GET', 'news/category/sortides');
        $this->assertStringContainsString('news-banner', $list);
        $this->assertStringContainsString($url, $list);
        $this->assertMatchesRegularExpression('#<h1[^>]*>Sortides</h1>#', $list, 'The category name is the hero title.');

        $article = $this->body('GET', 'news/sortida-al-bosc');
        $this->assertStringContainsString($url, $article);
        $this->assertStringContainsString('news/category/sortides', $article);

        db_connect()->table('news_categories')->where('id', $category)->update(['banner' => null]);
        $this->assertStringNotContainsString('news-banner', $this->body('GET', 'news/category/sortides'));
    }

    public function testAForgedBannerKeyIsRefused(): void
    {
        $this->createUser('ad@example.com', 'admin');
        $this->login('ad@example.com');
        $other = service('storage')->storeContents(self::png(), 'other.png', 'private', 'users/99');

        $this->send('POST', 'backend/news/categories/add', ['kp_tr' => ['ca' => ['name' => 'Robatori', 'description' => '']], 'position' => '1', 'active' => '1', 'banner' => $other->key]);
        $this->assertSame(0, db_connect()->table('news_categories')->where('banner', $other->key)->countAllResults(), 'Another user\'s private file cannot be a banner.');

        $this->send('POST', 'backend/news/categories/add', ['kp_tr' => ['ca' => ['name' => 'Sense bàner', 'description' => '']], 'position' => '1', 'active' => '1', 'banner' => ''])->assertRedirect();
        $this->assertSame(1, db_connect()->table('news_categories')->where('slug', 'sense-baner')->where('banner', null)->countAllResults());
    }

    public function testNewsRedirectsToTheMainListPageOnlyWhenItIsSet(): void
    {
        $this->newsPost('Pública', ['visibility' => 'public', 'slug' => 'publica']);
        $this->send('GET', 'news')->assertOK();

        $now = Time::now()->toDateTimeString();
        db_connect()->table('pages')->insert(['parent_id' => null, 'position' => 0, 'template' => 'default', 'layout' => 'sections', 'status' => 'published', 'publish_at' => Time::now()->subHours(1)->toDateTimeString(), 'visibility' => 'public', 'noindex' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $page = (int) db_connect()->insertID();
        db_connect()->table('page_translations')->insert(['page_id' => $page, 'locale' => 'ca', 'title' => 'Notícies', 'slug' => 'noticies', 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => '']);

        service('settings')->set('News.listPage', (string) $page);
        $response = $this->send('GET', 'news');
        $response->assertRedirectTo(site_url('noticies'));
        $this->assertSame(301, $response->response()->getStatusCode());
        $this->send('GET', 'news/publica')->assertOK(); // articles keep their URLs

        // A draft page or a page that is gone: nothing changes.
        db_connect()->table('pages')->where('id', $page)->update(['status' => 'draft']);
        $this->send('GET', 'news')->assertOK();
        service('settings')->set('News.listPage', '9999');
        $this->send('GET', 'news')->assertOK();

        // The setting offers the pages.
        $this->assertArrayHasKey((string) $page, (new \Modules\News\Settings\ListPageOptions())->options());
    }
}
