<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use CodeIgniter\I18n\Time;

/**
 * The back office of the pages: permissions (also forged POSTs), slugs per parent and language,
 * the sanitised body, publishing and scheduling, revisions and restore, duplicate, preview and
 * the tree.
 *
 * @internal
 */
final class PagesCrudTest extends PagesTestCase
{
    public function testTheListNeedsItsPermission(): void
    {
        $this->send('GET', 'backend/pages')->assertRedirectTo(site_url('login'));

        $this->loginWith();
        $this->send('GET', 'backend/pages')->assertStatus(403);
    }

    public function testAnEditorSeesTheTreeWithPathsAndTheHomeBadge(): void
    {
        $about = $this->page('Qui som');
        $this->page('Equip', ['parent_id' => $about]);
        service('settings')->set('Pages.homePage', 'qui-som');
        $this->editor();

        $list = $this->body('GET', 'backend/pages');
        $this->assertStringContainsString('/qui-som/equip', $list);
        $this->assertStringContainsString(lang('Pages.homeBadge'), $list);
        $this->assertLessThan(strpos($list, 'Equip</'), strpos($list, 'Qui som'), 'The child follows its parent.');
    }

    public function testForgedWritesWithoutPermissionAreRefused(): void
    {
        $id = $this->page('Intocable', ['status' => 'draft', 'publish_at' => null]);
        db_connect()->table('page_revisions')->insert(['page_id' => $id, 'action' => 'create', 'changes' => '[]', 'snapshot' => '{"page":{},"translations":{},"groups":[]}', 'created_at' => date('Y-m-d H:i:s')]);
        $revision = (int) db_connect()->insertID();
        $before   = [db_connect()->table('pages')->countAllResults(), $this->row($id), $this->text($id)];

        $this->loginWith('pages.list', 'pages.show');
        $code = $this->code($id);

        $writes = [
            'add'             => ['backend/pages/add', $this->form(['title' => 'Nova'])],
            'edit'            => ['backend/pages/edit/' . $code, $this->form(['title' => 'Canviada'])],
            'delete'          => ['backend/pages/delete/' . $code, []],
            'publish'         => ['backend/pages/publish/' . $code, []],
            'unpublish'       => ['backend/pages/unpublish/' . $code, []],
            'duplicate'       => ['backend/pages/duplicate/' . $code, []],
            'restorerevision' => ['backend/pages/restorerevision/' . $code, ['revision' => $revision]],
            'bulk publish'    => ['backend/pages/bulk', ['action' => 'publish', 'ids' => [$code]]],
        ];

        foreach ($writes as $what => [$path, $params]) {
            $this->assertCrudRefused($this->send('POST', $path, $params), 'backend/pages', $what);
            unset($_SESSION['error']);
        }

        $this->assertSame($before, [db_connect()->table('pages')->countAllResults(), $this->row($id), $this->text($id)], 'Nothing changed.');
    }

    public function testCreatingAPageGeneratesItsSlugSanitisesTheBodyAndKeepsARevision(): void
    {
        $this->editor();

        $this->send('POST', 'backend/pages/add', $this->form([
            'title' => 'L’escola d’estiu',
            'body'  => '<p style="text-align:center;color:red" onclick="x()">Hola <script>alert(1)</script></p><p class="ql-align-right">Dreta</p>',
        ]))->assertRedirect();

        $text = db_connect()->table('page_translations')->where('slug', 'l-escola-d-estiu')->get()->getRowArray();
        $this->assertIsArray($text, 'Slug made from the Catalan title.');
        $this->assertSame('<p class="text-center">Hola </p><p class="text-right">Dreta</p>', $text['body']);

        $page = $this->row((int) $text['page_id']);
        $this->assertSame('draft', $page['status'], 'New pages are drafts.');
        $this->assertSame((int) $this->loggedInUserId(), (int) $page['author_id']);
        $this->assertSame(1, db_connect()->table('page_revisions')->where('page_id', $page['id'])->countAllResults());
    }

    public function testSlugsAreUniquePerParentAndLanguage(): void
    {
        $about = $this->page('Qui som');
        $news  = $this->page('Arxiu');
        $this->page('Equip', ['parent_id' => $about], ['es' => ['title' => 'Equipo', 'slug' => 'equipo']]);
        $this->editor();

        // Same slug, same parent, same language: refused.
        $page = $this->body('POST', 'backend/pages/add', $this->form(['title' => 'Un altre equip', 'slug' => 'equip'], ['parent_id' => (string) $about]));
        $this->assertStringContainsString(lang('Pages.errors.slugTaken', ['equip', 'Català']), $page);
        $this->assertSame(1, db_connect()->table('page_translations')->where('slug', 'equip')->countAllResults());

        // Another parent, or the root: allowed.
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Equip', 'slug' => 'equip'], ['parent_id' => (string) $news]))->assertRedirect();
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Equip']))->assertRedirect();
        $this->assertSame(3, db_connect()->table('page_translations')->where('slug', 'equip')->countAllResults());

        // Another language may use the slug of a sibling's Spanish text.
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Equipo', 'slug' => 'equipo'], ['parent_id' => (string) $about]))->assertRedirect();

        // A generated slug that clashes gets a suffix.
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Equip'], ['parent_id' => (string) $about]))->assertRedirect();
        $this->assertSame(1, db_connect()->table('page_translations')->where('slug', 'equip-2')->countAllResults());

        // A root page never takes another route's first segment.
        $page = $this->body('POST', 'backend/pages/add', $this->form(['title' => 'Notícies', 'slug' => 'news']));
        $this->assertStringContainsString(lang('Pages.errors.slugReserved', ['news']), $page);
        $this->assertSame(0, db_connect()->table('page_translations')->where('slug', 'news')->countAllResults());
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Backend']))->assertRedirect();
        $this->assertSame(1, db_connect()->table('page_translations')->where('slug', 'backend-2')->countAllResults(), 'Generated: suffixed.');

        // An invalid slug is refused by the rule.
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Mal', 'slug' => 'Amb Espais/i barres']));
        $this->assertSame(0, db_connect()->table('page_translations')->where('title', 'Mal')->countAllResults());
    }

    public function testATreeCannotHaveCycles(): void
    {
        $parent = $this->page('Pare');
        $child  = $this->page('Fill', ['parent_id' => $parent]);
        $this->editor();

        $this->send('POST', 'backend/pages/edit/' . $this->code($parent), $this->form(['title' => 'Pare', 'slug' => 'pare'], ['parent_id' => (string) $child]));
        $this->assertNull($this->row($parent)['parent_id'], 'A page cannot hang from its descendant.');

        $form = $this->body('GET', 'backend/pages/edit/' . $this->code($parent));
        $this->assertSame(1, preg_match('/<select[^>]*name="parent_id"[^>]*>(.*?)<\/select>/s', $form, $select));
        $this->assertStringNotContainsString('Fill', $select[1], 'Its subtree is not offered as parent.');
        $this->assertStringNotContainsString('Pare', $select[1], 'Nor itself.');
    }

    public function testPublishingAndScheduling(): void
    {
        $now   = $this->page('Ara', ['status' => 'draft', 'publish_at' => null]);
        $later = $this->page('Després', ['status' => 'draft', 'publish_at' => Time::now()->addDays(3)->toDateTimeString()]);
        $this->editor();

        $this->send('POST', 'backend/pages/publish/' . $this->code($now))->assertRedirect();
        $this->send('POST', 'backend/pages/publish/' . $this->code($later))->assertRedirect();

        $this->assertSame('published', $this->row($now)['status']);
        $this->assertNotNull($this->row($now)['publish_at']);
        $this->assertSame('scheduled', $this->row($later)['status']);

        $this->send('POST', 'backend/pages/unpublish/' . $this->code($now))->assertRedirect();
        $this->assertSame('draft', $this->row($now)['status']);
    }

    public function testEverySaveKeepsARevisionAndARevisionCanBeRestored(): void
    {
        $this->editor();
        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Original', 'body' => '<p>Primer text</p>']))->assertRedirect();
        $id = (int) db_connect()->table('page_translations')->where('slug', 'original')->get()->getRowArray()['page_id'];

        $this->send('POST', 'backend/pages/edit/' . $this->code($id), $this->form(['title' => 'Canviada', 'slug' => 'original', 'body' => '<p>Segon text</p>'], ['visibility' => 'private']))->assertRedirect();
        $revisions = db_connect()->table('page_revisions')->where('page_id', $id)->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $revisions);
        $this->assertSame((int) $this->loggedInUserId(), (int) $revisions[1]['user_id']);
        $this->assertContains('title.ca', json_decode($revisions[1]['changes'], true));
        $this->assertContains('visibility', json_decode($revisions[1]['changes'], true));

        $page = $this->body('GET', 'backend/pages/revisions/' . $this->code($id) . '?revision=' . $revisions[1]['id']);
        $this->assertStringContainsString('Primer text', $page, 'The diff shows the text before…');
        $this->assertStringContainsString('Segon text', $page, '… and after.');

        $this->send('POST', 'backend/pages/restorerevision/' . $this->code($id), ['revision' => $revisions[0]['id']])->assertRedirect();
        $this->assertSame('Original', $this->text($id)['title']);
        $this->assertSame('<p>Primer text</p>', $this->text($id)['body']);
        $this->assertSame('public', $this->row($id)['visibility']);
        $this->assertSame(3, db_connect()->table('page_revisions')->where('page_id', $id)->countAllResults(), 'The restore is a new revision.');

        // Another page's revision is refused.
        $other = $this->page('Una altra');
        db_connect()->table('page_revisions')->insert(['page_id' => $other, 'action' => 'create', 'changes' => '[]', 'snapshot' => '{"page":{},"translations":{"ca":{"title":"Intrús","slug":"intrus"}},"groups":[]}', 'created_at' => date('Y-m-d H:i:s')]);
        $this->send('POST', 'backend/pages/restorerevision/' . $this->code($id), ['revision' => db_connect()->insertID()]);
        $this->assertSame(lang('Pages.errors.revisionNotFound'), $_SESSION['error'] ?? null);
        $this->assertSame('Original', $this->text($id)['title']);
    }

    public function testDuplicateMakesADraftCopy(): void
    {
        $id = $this->page('Model', ['visibility' => 'groups'], [], ['editor']);
        $this->editor();

        $this->send('POST', 'backend/pages/duplicate/' . $this->code($id))->assertRedirect();

        $copy = db_connect()->table('page_translations')->where('slug', 'model-' . lang('Pages.copySlug'))->get()->getRowArray();
        $this->assertIsArray($copy);
        $this->assertSame(lang('Pages.copyOf', ['Model']), $copy['title']);
        $row = $this->row((int) $copy['page_id']);
        $this->assertSame('draft', $row['status']);
        $this->assertSame('groups', $row['visibility']);
        $this->assertSame(1, db_connect()->table('page_groups')->where('page_id', $row['id'])->countAllResults());
        $this->assertSame((int) $this->loggedInUserId(), (int) $row['author_id']);
    }

    public function testPurgingAPageRemovesItsRevisionsAndFreesItsChildren(): void
    {
        $parent = $this->page('Pare');
        $child  = $this->page('Fill', ['parent_id' => $parent]);
        (new \Modules\Pages\Services\Revisions())->record($parent, null, 'create');
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/pages/delete/' . $this->code($parent))->assertRedirect();
        $this->assertNotNull($this->row($parent)['deleted_at'], 'Trashed first.');
        $this->send('POST', 'backend/pages/purge/' . $this->code($parent))->assertRedirect();

        $this->assertSame(0, db_connect()->table('pages')->where('id', $parent)->countAllResults());
        $this->assertSame(0, db_connect()->table('page_revisions')->where('page_id', $parent)->countAllResults());
        $this->assertSame(0, db_connect()->table('page_translations')->where('page_id', $parent)->countAllResults());
        $this->assertNull($this->row($child)['parent_id']);
    }

    public function testPreviewShowsADraftWithNoindex(): void
    {
        $id = $this->page('Esborrany secret', ['status' => 'draft', 'publish_at' => null, 'visibility' => 'groups'], [], ['admin']);
        $this->editor();

        $response = $this->send('GET', 'backend/pages/preview/' . $this->code($id));
        $response->assertOK();
        $this->assertStringContainsString('noindex', $response->response()->getHeaderLine('X-Robots-Tag'));
        $body = html_entity_decode((string) $response->response()->getBody());
        $this->assertStringContainsString('Text de Esborrany secret', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $body);
        $this->assertStringContainsString(lang('Pages.previewNotice'), $body);
    }

    public function testTheBodyIsAnEditorInEveryLanguage(): void
    {
        $this->editor();
        $form = $this->body('GET', 'backend/pages/add');

        foreach (['ca', 'es', 'en'] as $locale) {
            $this->assertMatchesRegularExpression('/<textarea[^>]*name="kp_tr\[' . $locale . '\]\[body\]"[^>]*data-component="core\/forms\/editor"/', $form);
        }

        $this->assertStringNotContainsString('name="status"', $form, 'New pages are drafts: no status field.');
        $this->assertStringNotContainsString('name="author_id"', $form);
    }
}
