<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The back office of the posts (acceptance criterion 4 on News): read-only users, forged POSTs,
 * the own-posts rule, `news-posts.author` hidden, "My posts" with `where()`, the sanitised body,
 * slugs and the publish operation.
 *
 * @internal
 */
final class PostsCrudTest extends NewsTestCase
{
    public function testAReadOnlyUserSeesTheListWithoutWriteButtons(): void
    {
        $id = $this->newsPost('Notícia de prova');
        $this->newsPost('Esborrany de prova', ['status' => 'draft', 'published_at' => null]);
        $this->loginWith('news-posts.list', 'news-posts.show');

        $list = $this->body('GET', 'backend/news/posts');

        $this->assertStringContainsString('Notícia de prova', $list);
        $this->assertStringContainsString('Esborrany de prova', $list);

        // Neither the published post (unpublish) nor the draft (publish) offers a write action.
        foreach (['add', 'edit/', 'publish/', 'unpublish/', 'delete/', 'trash', 'export', 'import', 'readers/'] as $operation) {
            $this->assertStringNotContainsString('backend/news/posts/' . $operation, $list, $operation);
        }

        $this->assertDoesNotMatchRegularExpression('/<input[^>]+name="ids\[\]"/', $list, 'No bulk selection boxes.');
        $this->assertStringContainsString('backend/news/posts/show/' . $this->code($id), $list);
    }

    public function testForgedPostsOfAReadOnlyUserChangeNothing(): void
    {
        $draft     = $this->newsPost('Intacta', ['status' => 'draft', 'published_at' => null]);
        $published = $this->newsPost('Publicada');
        $trashed   = $this->newsPost('A la paperera', ['deleted_at' => '2026-01-01 10:00:00']);
        $this->loginWith('news-posts.list', 'news-posts.show');
        $before = $this->snapshot();

        foreach ([
            ['POST', 'add', ['kp_tr' => ['ca' => ['title' => 'Forjada']], 'type' => 'article', 'visibility' => 'public']],
            ['POST', 'edit/' . $this->code($draft), ['kp_tr' => ['ca' => ['title' => 'Canviada']]]],
            ['POST', 'delete/' . $this->code($draft), []],
            ['POST', 'publish/' . $this->code($draft), []],
            ['POST', 'unpublish/' . $this->code($published), []],
            ['POST', 'restore/' . $this->code($trashed), []],
            ['POST', 'purge/' . $this->code($trashed), []],
            ['POST', 'emptytrash', []],
            ['POST', 'bulk', ['action' => 'publish', 'ids' => [$this->code($draft)]]],
            ['POST', 'bulk', ['action' => 'delete', 'ids' => [$this->code($draft), $this->code($published)]]],
            ['POST', 'bulk', ['action' => 'restore', 'ids' => [$this->code($trashed)]]],
            ['POST', 'bulk', ['action' => 'purge', 'ids' => [$this->code($trashed)]]],
            ['GET', 'trash', []],
            ['GET', 'export', []],
            ['GET', 'readers/' . $this->code($published), []],
        ] as [$method, $operation, $params]) {
            try {
                $response = $this->send($method, 'backend/news/posts/' . $operation, $params);
            } catch (PageNotFoundException $e) {
                // kpcrudy answers some screens it does not offer this user (e.g. the trash) with
                // a 404 "not allowed".
                $this->assertSame(lang('Kpcrudy.notAllowed'), $e->getMessage(), "{$method} {$operation}");

                continue;
            }

            $this->assertCrudRefused($response, 'backend/news/posts', "{$method} {$operation}");
            unset($_SESSION['error'], $_SESSION['success']);
        }

        $this->assertSame($before, $this->snapshot(), 'Nothing changed.');
        $this->assertSame('draft', $this->row($draft)['status']);
        $this->assertSame('published', $this->row($published)['status']);
        $this->assertNotNull($this->row($trashed)['deleted_at']);
    }

    public function testForgedCategoryWritesOfAnEditorAreRefused(): void
    {
        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
        db_connect()->table('news_categories')->insert(['slug' => 'intacta', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00']);
        $id    = (int) db_connect()->insertID();
        $code  = \Kpcrudy\Crud::maskId($id, 'news_categories');
        $count = db_connect()->table('news_categories')->countAllResults();

        $this->send('GET', 'backend/news/categories')->assertOK();

        foreach ([
            ['add', ['kp_tr' => ['ca' => ['name' => 'Forjada']], 'slug' => 'forjada']],
            ['edit/' . $code, ['kp_tr' => ['ca' => ['name' => 'Canviada']], 'slug' => 'canviada']],
            ['delete/' . $code, []],
        ] as [$operation, $params]) {
            $this->assertCrudRefused($this->send('POST', 'backend/news/categories/' . $operation, $params), 'backend/news/categories', $operation);
            unset($_SESSION['error'], $_SESSION['success']);
        }

        $this->assertSame($count, db_connect()->table('news_categories')->countAllResults());
        $this->assertSame('intacta', db_connect()->table('news_categories')->where('id', $id)->get()->getRowArray()['slug'] ?? null);
    }

    public function testUsersWithoutListGetForbiddenAndGuestsTheLogin(): void
    {
        $this->send('GET', 'backend/news/posts')->assertRedirectTo('/login');

        $this->loginWith();
        $this->send('GET', 'backend/news/posts')->assertStatus(403);
        $this->send('GET', 'backend/news/categories')->assertStatus(403);
        $this->send('GET', 'backend/news/mine')->assertStatus(403);
    }

    public function testAnEditorChangesOnlyTheirOwnPosts(): void
    {
        $other = $this->createUser('altre@example.com', 'editor');
        $ana   = $this->createUser('ana@example.com', 'editor');
        $mine  = $this->newsPost('Meva', ['author_id' => $ana->id, 'status' => 'draft']);
        $theirs = $this->newsPost('Seva', ['author_id' => $other->id, 'status' => 'draft']);
        $this->login('ana@example.com');

        $list = $this->body('GET', 'backend/news/posts');
        $this->assertStringContainsString('backend/news/posts/edit/' . $this->code($mine), $list);
        $this->assertStringNotContainsString('backend/news/posts/edit/' . $this->code($theirs), $list, 'Editors see every post but only change their own.');

        $this->send('POST', 'backend/news/posts/edit/' . $this->code($theirs), ['kp_tr' => ['ca' => ['title' => 'Presa']], 'type' => 'article', 'visibility' => 'private']);
        $this->send('POST', 'backend/news/posts/publish/' . $this->code($theirs));
        $this->assertSame('Seva', db_connect()->table('news_translations')->where('news_id', $theirs)->get()->getRowArray()['title']);
        $this->assertSame('draft', $this->row($theirs)['status']);

        $published = $this->newsPost('Seva publicada', ['author_id' => $other->id]);
        $this->assertCrudRefused($this->send('POST', 'backend/news/posts/unpublish/' . $this->code($published)), 'backend/news/posts', 'unpublish theirs');
        $this->assertSame('published', $this->row($published)['status']);

        $this->send('POST', 'backend/news/posts/publish/' . $this->code($mine))->assertRedirect();
        $this->assertSame('published', $this->row($mine)['status']);
    }

    public function testTheAuthorFieldIsHiddenWithoutTheAuthorPermission(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $ana  = $this->createUser('ana@example.com', 'editor');
        $id   = $this->newsPost('Amb autor', ['author_id' => $boss->id]);
        $this->login('ana@example.com');

        $this->assertStringNotContainsString('>boss<', $this->body('GET', 'backend/news/posts'));
        $this->assertStringNotContainsString('boss', $this->body('GET', 'backend/news/posts/show/' . $this->code($id)));

        // A forged author_id on a new post is ignored: the post is the editor's.
        $this->send('POST', 'backend/news/posts/add', [
            'kp_tr' => ['ca' => ['title' => 'Nova', 'summary' => '', 'body' => '']], 'type' => 'article', 'priority' => 'info',
            'visibility' => 'private', 'author_id' => (string) $boss->id,
        ]);
        $new = db_connect()->table('news')->where('slug', 'nova')->get()->getRowArray();
        $this->assertIsArray($new);
        $this->assertSame((int) $ana->id, (int) $new['author_id']);
        $this->assertSame('draft', $new['status'], 'New posts are drafts until published.');

        $this->send('POST', 'logout');
        $this->login('boss@example.com');
        $this->assertStringContainsString('boss', $this->body('GET', 'backend/news/posts/show/' . $this->code($id)));
    }

    public function testTheExportAndEditsNeverExposeOrChangeTheAuthorWithoutThePermission(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $ana  = $this->loginWith('news-posts.list', 'news-posts.show', 'news-posts.export', 'news-posts.edit');
        $this->newsPost('Del cap', ['author_id' => $boss->id]);
        $own = $this->newsPost('Pròpia', ['author_id' => $ana->id, 'status' => 'draft', 'published_at' => null]);

        $csv = $this->body('GET', 'backend/news/posts/export');
        $this->assertStringContainsString('Del cap', $csv, 'The export works.');
        $this->assertStringNotContainsString('boss', $csv);
        $this->assertStringNotContainsString(lang('News.fields.author_id'), $csv);

        // A forged author_id on an edit of one's own post is ignored.
        $this->send('POST', 'backend/news/posts/edit/' . $this->code($own), [
            'kp_tr' => ['ca' => ['title' => 'Pròpia', 'summary' => '', 'body' => '']], 'type' => 'article', 'priority' => 'info',
            'visibility' => 'private', 'author_id' => (string) $boss->id,
        ]);
        $this->assertSame((int) $ana->id, (int) $this->row($own)['author_id']);
    }

    public function testTheBodyIsSanitisedAndTheSlugIsGenerated(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->newsPost('Títol repetit', ['slug' => 'titol-repetit']);

        $this->send('POST', 'backend/news/posts/add', [
            'kp_tr' => [
                'ca' => ['title' => 'Títol repetit', 'summary' => 'Resum', 'body' => '<p onclick="alert(1)">Hola <script>alert(2)</script><a href="javascript:alert(3)">x</a><strong>bé</strong></p>'],
                'en' => ['title' => 'Repeated title', 'summary' => '', 'body' => '<img src="x" onerror="alert(4)">'],
            ],
            'type' => 'article', 'priority' => 'info', 'visibility' => 'public',
        ])->assertRedirect();

        $post = db_connect()->table('news')->where('slug', 'titol-repetit-2')->get()->getRowArray();
        $this->assertIsArray($post, 'A unique slug from the Catalan title (accents transliterated).');

        foreach (db_connect()->table('news_translations')->where('news_id', $post['id'])->get()->getResultArray() as $text) {
            $this->assertStringNotContainsString('script', (string) $text['body']);
            $this->assertStringNotContainsString('onclick', (string) $text['body']);
            $this->assertStringNotContainsString('onerror', (string) $text['body']);
            $this->assertStringNotContainsString('javascript:', (string) $text['body']);
        }

        $ca = db_connect()->table('news_translations')->where('news_id', $post['id'])->where('locale', 'ca')->get()->getRowArray();
        $this->assertStringContainsString('<strong>bé</strong>', (string) $ca['body']);
    }

    public function testTheBodyUsesTheRichTextEditorAndKeepsAlignmentAsClasses(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $form = $this->body('GET', 'backend/news/posts/add');
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="kp_tr\[ca\]\[body\]"[^>]*data-component="core\/forms\/editor"/', $form, 'Each language\'s body is an editor.');
        $this->assertStringContainsString('quill/quill.js', $form);
        $this->assertStringNotContainsString('name="body"', $form, 'No main-table body field.');

        $this->send('POST', 'backend/news/posts/add', [
            'kp_tr' => ['ca' => ['title' => 'Alineada', 'summary' => '', 'body' => '<p class="text-center">Centre</p><p style="text-align: right; color: red">Dreta</p><p class="ql-align-justify evil">J</p>']],
            'type'  => 'article', 'priority' => 'info', 'visibility' => 'public',
        ])->assertRedirect();

        $post = db_connect()->table('news')->where('slug', 'alineada')->get()->getRowArray();
        $this->assertIsArray($post);
        $body = (string) db_connect()->table('news_translations')->where('news_id', $post['id'])->get()->getRowArray()['body'];
        $this->assertSame('<p class="text-center">Centre</p><p class="text-right">Dreta</p><p class="text-justify">J</p>', $body);
    }

    public function testPublishingAPrivateNoticeNotifiesItsAudienceOnce(): void
    {
        $boss  = $this->createUser('boss@example.com', 'admin');
        $ana   = $this->createUser('ana@example.com', 'user');
        $draft = $this->newsPost('Avís privat', ['type' => 'notice', 'status' => 'draft', 'published_at' => null, 'author_id' => $boss->id]);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/news/posts/publish/' . $this->code($draft))->assertRedirect();

        $this->assertSame('published', $this->row($draft)['status']);
        $this->assertNotNull($this->row($draft)['notified_at']);
        $this->assertSame(1, $this->notificationsOf((int) $ana->id));

        $this->send('POST', 'backend/news/posts/unpublish/' . $this->code($draft))->assertRedirect();
        $this->send('POST', 'backend/news/posts/publish/' . $this->code($draft))->assertRedirect();
        $this->assertSame(1, $this->notificationsOf((int) $ana->id), 'Sent only once.');
    }

    public function testAGroupNoticeNotifiesOnlyItsGroupsAndAFutureDateSchedulesIt(): void
    {
        $boss   = $this->createUser('boss@example.com', 'admin');
        $editor = $this->createUser('edi@example.com', 'editor');
        $ana    = $this->createUser('ana@example.com', 'user');
        $notice = $this->newsPost('Per als editors', ['type' => 'notice', 'visibility' => 'groups', 'status' => 'draft', 'published_at' => null], ['editor']);
        $later  = $this->newsPost('Més endavant', ['type' => 'notice', 'status' => 'draft', 'published_at' => date('Y-m-d H:i:s', time() + 86400)]);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/news/posts/publish/' . $this->code($notice));
        $this->send('POST', 'backend/news/posts/publish/' . $this->code($later));

        $this->assertSame(1, $this->notificationsOf((int) $editor->id));
        $this->assertSame(0, $this->notificationsOf((int) $ana->id));
        $this->assertSame('scheduled', $this->row($later)['status']);
        $this->assertNull($this->row($later)['notified_at'], 'A scheduled notice notifies when the task releases it.');
        $this->assertSame(0, $this->notificationsOf((int) $boss->id), 'Admins are not in the editor group.');
    }

    public function testMyPostsOnlyShowsTheUsersPostsEvenWithAForgedId(): void
    {
        $other = $this->createUser('altre@example.com', 'editor');
        $ana   = $this->createUser('ana@example.com', 'editor');
        $mine  = $this->newsPost('La meva', ['author_id' => $ana->id]);
        $theirs = $this->newsPost('La seva', ['author_id' => $other->id]);
        $this->login('ana@example.com');

        $list = $this->body('GET', 'backend/news/mine');
        $this->assertStringContainsString('La meva', $list);
        $this->assertStringNotContainsString('La seva', $list);
        $this->assertStringNotContainsString('La seva', $this->body('GET', 'backend/news/mine/show/' . $this->code($theirs)));

        $this->send('POST', 'backend/news/mine/delete/' . $this->code($theirs));
        $this->assertNull($this->row($theirs)['deleted_at']);

        $this->send('POST', 'backend/news/mine/add', [
            'kp_tr' => ['ca' => ['title' => 'Des de les meves', 'summary' => '', 'body' => '']], 'type' => 'article', 'priority' => 'info', 'visibility' => 'private',
        ]);
        $new = db_connect()->table('news')->where('slug', 'des-de-les-meves')->get()->getRowArray();
        $this->assertIsArray($new);
        $this->assertSame((int) $ana->id, (int) $new['author_id']);
        $this->assertSame((int) $ana->id, (int) $this->row($mine)['author_id']);
    }

    public function testTheReadersPageCountsReceipts(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $id  = $this->newsPost('Llegida');
        db_connect()->table('news_reads')->insert(['news_id' => $id, 'user_id' => $ana->id, 'read_at' => date('Y-m-d H:i:s')]);
        $this->login('boss@example.com');

        $page = $this->body('GET', 'backend/news/posts/readers/' . $this->code($id));
        $this->assertStringContainsString('ana', $page);
        $this->assertStringContainsString('1 user has read it', $page);
    }

    public function testCategoriesGetASlug(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/news/categories/add', [
            'kp_tr' => ['ca' => ['name' => 'Àmbit Social', 'description' => '']], 'position' => '2', 'active' => '1',
        ])->assertRedirect();

        $this->assertSame(1, db_connect()->table('news_categories')->where('slug', 'ambit-social')->countAllResults());
    }

    public function testDatesAreEditedInTheSiteTimeZoneAndStoredInUtc(): void
    {
        setting()->set('Core.timezone', 'Europe/Madrid');
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        try {
            $this->send('POST', 'backend/news/posts/add', [
                'kp_tr' => ['ca' => ['title' => 'Hora local', 'summary' => '', 'body' => '']], 'type' => 'notice', 'priority' => 'info',
                'visibility' => 'private', 'published_at' => '2026-01-15T09:00', 'expires_at' => '2026-07-15T09:00',
            ])->assertRedirect();

            $row = db_connect()->table('news')->where('slug', 'hora-local')->get()->getRowArray();
            $this->assertIsArray($row);
            $this->assertSame('2026-01-15 08:00:00', $row['published_at'], 'Winter: UTC+1.');
            $this->assertSame('2026-07-15 07:00:00', $row['expires_at'], 'Summer: UTC+2.');

            $form = $this->body('GET', 'backend/news/posts/edit/' . $this->code((int) $row['id']));
            $this->assertStringContainsString('value="2026-01-15T09:00"', $form);
            $this->assertStringContainsString('value="2026-07-15T09:00"', $form);
        } finally {
            setting()->forget('Core.timezone');
        }
    }

    /**
     * Every row of the News tables, to prove forged requests changed nothing.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $tables = [];

        foreach (['news', 'news_translations', 'news_groups', 'news_reads'] as $table) {
            $tables[$table] = db_connect()->table($table)->orderBy('id')->get()->getResultArray();
        }

        return $tables;
    }
}
