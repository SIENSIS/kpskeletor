<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\Database\Migration;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Modules\ManifestValidator;
use Modules\News\Config\Module;
use Modules\News\Database\Migrations\CreateNews;
use Modules\News\Database\Migrations\CreateNewsCategories;
use Modules\News\Database\Migrations\CreateNewsCategoryTranslations;
use Modules\News\Database\Migrations\CreateNewsGroups;
use Modules\News\Database\Migrations\CreateNewsReads;
use Modules\News\Database\Migrations\CreateNewsTranslations;
use Modules\News\Menu\CategoryLink;
use Modules\News\Services\BodySanitizer;
use Modules\News\Services\Slugger;

/**
 * The manifest, the migrations (up, down, up), the sanitiser, slugs and the menu link type.
 *
 * @internal
 */
final class NewsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testManifestIsValid(): void
    {
        $manifest = new Module();

        $this->assertSame([], (new ManifestValidator())->validate($manifest, dirname(__DIR__)));
        $this->assertSame('news-posts', $manifest->permissionPrefix('posts'));

        foreach (['superadmin', 'admin', 'editor', 'user'] as $group) {
            $this->assertContains('news-read.*', $manifest->defaultGrants[$group], "{$group} reads the news (D-00-42).");
        }

        $this->assertNotContains('news-posts.author', $manifest->defaultGrants['editor']);

        // The body is sanitised when the form is posted (D-17-02): a CSV import would skip it.
        foreach (['posts', 'mine'] as $resource) {
            $this->assertContains('import', $manifest->resources[$resource]['except'], "news-{$resource} must not enable the CSV import without sanitising the body.");
        }
    }

    public function testUpDownUp(): void
    {
        $classes    = [CreateNewsCategories::class, CreateNewsCategoryTranslations::class, CreateNews::class, CreateNewsTranslations::class, CreateNewsGroups::class, CreateNewsReads::class];
        $migrations = array_map(static fn (string $class): Migration => new $class(\Config\Database::forge('tests')), $classes);
        $tables     = ['news_categories', 'news_category_translations', 'news', 'news_translations', 'news_groups', 'news_reads'];

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertFalse($this->db->tableExists($table), $table);
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }

    public function testTheSanitiserKeepsFormattingAndDropsScripts(): void
    {
        $clean = BodySanitizer::clean('<h2>Títol</h2><p style="color:red" onmouseover="x()">Text <a href="https://example.com" target="_blank">enllaç</a> <a href="javascript:alert(1)">mal</a></p><script>alert(1)</script><iframe src="https://evil.example"></iframe>');

        $this->assertStringContainsString('<h2>Títol</h2>', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('noopener', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertSame('', BodySanitizer::clean('  '));
    }

    public function testSlugs(): void
    {
        $this->assertSame('l-escola-obre-l-estiu', Slugger::slug('L’escola obre l’estiu!'));
        $this->assertSame('cafe-i-pa', Slugger::slug('Cafè i pa'));
        $this->assertSame('post', Slugger::slug('¡¿?!'));

        $this->db->table('news')->insert(['slug' => 'cafe-i-pa', 'type' => 'article', 'visibility' => 'public', 'status' => 'draft']);
        // Read the id at once: MySQLi resets insert_id after any other query (a SELECT included).
        $id = (int) $this->db->insertID();
        $this->assertSame('cafe-i-pa-2', Slugger::unique('news', 'Cafè i pa'));
        $this->assertSame('cafe-i-pa', Slugger::unique('news', 'Cafè i pa', $id));
    }

    public function testTheCategoryLinkType(): void
    {
        $this->db->table('news_categories')->insert(['slug' => 'activitats', 'position' => 1, 'active' => 1]);
        $id = (int) $this->db->insertID();
        $this->db->table('news_category_translations')->insert(['category_id' => $id, 'locale' => 'ca', 'name' => 'Activitats']);
        $link = new CategoryLink();

        $this->assertSame('news.category', $link->key());
        $this->assertSame(['activitats' => 'Activitats'], $link->options());
        $this->assertSame(site_url('news/category/activitats'), $link->url('activitats'));
        $this->assertNull($link->url('no-existeix'));
    }
}
