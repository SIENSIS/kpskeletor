<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Core\Frontend\HomePage;
use Modules\Core\Modules\ManifestValidator;
use Modules\Pages\Config\Module;
use Modules\Pages\Menu\PageLink;
use Modules\Pages\Services\PageResolver;
use Modules\Pages\Services\PageTree;
use Modules\Pages\Services\Revisions;

/**
 * The Pages module: manifest, permissions, the menu link type, the tree and the resolver,
 * revisions, and its migrations (down and up again).
 *
 * @internal
 */
final class PagesModuleTest extends PagesTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testPermissionNamesAndGrants(): void
    {
        $catalogue = array_column(db_connect()->table('permissions')->like('name', 'pages.', 'after')->get()->getResultArray(), 'name');
        sort($catalogue);
        $this->assertSame(['pages.add', 'pages.delete', 'pages.edit', 'pages.export', 'pages.list', 'pages.publish', 'pages.purge', 'pages.restore', 'pages.show', 'pages.trash'], $catalogue);

        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertTrue($editor->can('pages.publish'));
        $this->assertFalse($editor->can('pages.delete'));
        $this->assertFalse($this->createUser('ana@example.com', 'user')->can('pages.list'));
    }

    public function testTheTreeAndTheResolver(): void
    {
        $a = $this->page('A');
        $b = $this->page('B', ['parent_id' => $a], ['es' => ['title' => 'Be', 'slug' => 'be']]);
        $c = $this->page('C', ['parent_id' => $b]);
        $tree = new PageTree();

        $this->assertSame([$a, $b, $c], $tree->lineage($c));
        $this->assertSame([$b, $c], $tree->descendants($a));
        $this->assertSame('a/b/c', $tree->path($c, 'ca'));
        $this->assertSame('a/be/c', $tree->path($c, 'es'), 'Missing levels fall back to Catalan.');
        $this->assertSame([$a => 'A'], $tree->options($b, 'ca'), 'No subtree in the parent options.');

        $resolver = new PageResolver();
        $this->assertSame($c, $resolver->resolve('a/b/c', 'ca'));
        $this->assertSame($c, $resolver->resolve('/a/be/c/', 'en'));
        $this->assertNull($resolver->resolve('a/c', 'ca'));
        $this->assertNull($resolver->resolve('A/B', 'ca'), 'Only valid slugs.');
        $this->assertNull($resolver->resolve(str_repeat('a/', 30) . 'a', 'ca'));
    }

    public function testTheMenuLinkType(): void
    {
        $about = $this->page('Qui som');
        $team  = $this->page('Equip', ['parent_id' => $about]);
        $draft = $this->page('Esborrany', ['status' => 'draft', 'publish_at' => null]);
        $link  = new PageLink();

        $this->assertSame('pages.page', $link->key());
        $this->assertSame(['Qui som', '— Equip', 'Esborrany'], array_values($link->options()));
        $this->assertSame(site_url('qui-som/equip'), $link->url((string) $team));
        $this->assertNull($link->url((string) $draft));
        $this->assertNull($link->url('999'));
        $this->assertNull($link->url('x'));

        service('settings')->set('Pages.homePage', 'qui-som');
        $this->assertSame(site_url('/'), $link->url((string) $about));
    }

    public function testRevisionsSkipUnchangedSaves(): void
    {
        $id        = $this->page('Revisada');
        $revisions = new Revisions();

        $this->assertNotNull($revisions->record($id, null, 'create'));
        $this->assertNull($revisions->record($id, null), 'Nothing changed.');

        db_connect()->table('page_translations')->where('page_id', $id)->update(['body' => '<p>Text de <strong>Revisada</strong></p>']);
        $this->assertNotNull($revisions->record($id, null), 'A formatting change is a change.');
        $this->assertSame(['body.ca'], $revisions->history($id)[0]['changes']);
    }

    public function testTheHomePageEventIsIgnoredWhileTheModuleIsDisabled(): void
    {
        $this->page('Inici');
        service('settings')->set('Pages.homePage', 'inici');
        db_connect()->table('modules')->where('name', 'Pages')->update(['enabled' => 0]);
        (new \Modules\Core\Cache\CoreCache())->invalidate('modules');

        $home = new HomePage();
        \CodeIgniter\Events\Events::trigger(HomePage::EVENT, $home);
        $this->assertFalse($home->answered());

        db_connect()->table('modules')->where('name', 'Pages')->update(['enabled' => 1]);
        (new \Modules\Core\Cache\CoreCache())->invalidate('modules');
        \CodeIgniter\Events\Events::trigger(HomePage::EVENT, $home);
        $this->assertTrue($home->answered());
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];

        foreach ($files as $file) {
            $class = 'Modules\Pages\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(4, $migrations);

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();
        $this->assertFalse($this->db->tableExists('pages'));

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach (['pages', 'page_translations', 'page_groups', 'page_revisions'] as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }
}
