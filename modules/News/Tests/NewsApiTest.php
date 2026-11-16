<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use Kpcrudy\Crud;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;

/**
 * API resource `news`: list and details with the visibility rules, masked ids shared with the
 * back office, scopes and read-only.
 *
 * @internal
 */
final class NewsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    public function testTheListHasOnlyReadablePosts(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->newsPost('Pública', 'public');
        $this->newsPost('Privada', 'private');
        $this->newsPost('Editors', 'groups', ['editor']);
        $this->newsPost('Esborrany', 'private', [], 'draft');

        $response = $this->api('GET', 'api/v1/news', null, $this->tokenFor($user));
        $response->assertStatus(200);
        $titles = array_column($this->json($response)['data'], 'title');
        sort($titles);

        $this->assertSame(['Privada', 'Pública'], $titles);
    }

    public function testDetailsOfAnUnreadablePostAreNotFound(): void
    {
        $user    = $this->createUser('ana@example.com', 'user');
        $visible = $this->newsPost('Visible', 'private');
        $hidden  = $this->newsPost('Amagada', 'groups', ['editor']);
        $token   = $this->tokenFor($user);

        $ok = $this->api('GET', 'api/v1/news/' . Crud::maskId($visible, 'news'), null, $token);
        $ok->assertStatus(200);
        $this->assertSame('Visible', $this->json($ok)['data']['title']);
        $this->assertStringContainsString('<p>', (string) $this->json($ok)['data']['body']);

        $this->api('GET', 'api/v1/news/' . Crud::maskId($hidden, 'news'), null, $token)->assertStatus(404);
    }

    public function testTheBodyIsCleanedOnOutputToo(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $id   = $this->newsPost('Crua', 'private');
        // A row written without the form (e.g. a future import) keeps raw HTML in the database.
        db_connect()->table('news_translations')->where('news_id', $id)->update(['body' => '<p onclick="alert(1)">Hola</p><script>alert(2)</script>']);
        $token = $this->tokenFor($user);

        $one = $this->json($this->api('GET', 'api/v1/news/' . Crud::maskId($id, 'news'), null, $token))['data']['body'];
        $all = $this->json($this->api('GET', 'api/v1/news/' . Crud::maskId($id, 'news') . '?lang=*', null, $token))['data']['body'];

        $this->assertSame('<p>Hola</p>', $one);
        $this->assertSame('<p>Hola</p>', $all['ca']);
    }

    public function testItIsReadOnlyAndNeedsAToken(): void
    {
        $user = $this->createUser('ana@example.com', 'admin');
        $id   = $this->newsPost('Visible', 'private');

        $this->api('GET', 'api/v1/news')->assertStatus(401);
        $this->assertContains($this->api('POST', 'api/v1/news', ['title' => 'Nova'], $this->tokenFor($user))->response()->getStatusCode(), [403, 405]);
        $this->assertContains($this->api('DELETE', 'api/v1/news/' . Crud::maskId($id, 'news'), null, $this->tokenFor($user))->response()->getStatusCode(), [403, 405]);
        $this->assertSame(1, db_connect()->table('news')->where('deleted_at', null)->countAllResults());
    }

    public function testScopesNarrowTheToken(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->newsPost('Visible', 'private');

        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['news-api.list']))->assertStatus(200);
        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['news-api.*']))->assertStatus(200);
        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['activity-mine.list']))->assertStatus(403);
        // The reader screen's permission is not the API's (D-17-01).
        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['news-read.view']))->assertStatus(403);
    }

    public function testMarkingAPostAsReadNeedsTheReadScopeAndAReadablePost(): void
    {
        $user    = $this->createUser('ana@example.com', 'user');
        $visible = $this->newsPost('Visible', 'private');
        $hidden  = $this->newsPost('Amagada', 'groups', ['editor']);
        $token   = $this->tokenFor($user);
        $path    = 'api/v1/news/' . Crud::maskId($visible, 'news');

        $first = $this->api('POST', $path . '/read', null, $token);
        $first->assertStatus(200);
        $this->assertSame(['read' => true, 'marked' => true], $this->json($first)['data']);
        $this->assertSame(['read' => true, 'marked' => false], $this->json($this->api('POST', $path . '/read', null, $token))['data']);
        $this->assertTrue($this->json($this->api('GET', $path, null, $token))['data']['read']);

        // A post the user may not read is not found, and nothing is written.
        $this->api('POST', 'api/v1/news/' . Crud::maskId($hidden, 'news') . '/read', null, $token)->assertStatus(404);
        $this->assertSame(0, db_connect()->table('news_reads')->where('news_id', $hidden)->countAllResults());
    }

    /**
     * Acceptance criterion 10 (API part): a token limited to listing can list but gets 403 on any write.
     */
    public function testATokenLimitedToListingCannotWrite(): void
    {
        $user  = $this->createUser('ana@example.com', 'admin');
        $id    = $this->newsPost('Visible', 'private');
        $token = $this->tokenFor($user, ['news-api.list']);
        $path  = 'api/v1/news/' . Crud::maskId($id, 'news');

        $this->api('GET', 'api/v1/news', null, $token)->assertStatus(200);
        $this->api('POST', $path . '/read', null, $token)->assertStatus(403);
        $this->assertContains($this->api('POST', 'api/v1/news', ['title' => 'Nova'], $token)->response()->getStatusCode(), [403, 405]);
        $this->assertContains($this->api('PATCH', $path, ['title' => 'Canviada'], $token)->response()->getStatusCode(), [403, 405]);
        $this->assertContains($this->api('DELETE', $path, null, $token)->response()->getStatusCode(), [403, 405]);
        $this->assertSame(0, db_connect()->table('news_reads')->countAllResults());
        $this->assertSame(1, db_connect()->table('news')->where('deleted_at', null)->countAllResults());
    }

    public function testTheSuperadminsTokenIsLimitedByItsScopesToo(): void
    {
        $user = $this->createUser('root@example.com', 'superadmin');
        $id   = $this->newsPost('Visible', 'private');

        $this->api('POST', 'api/v1/news/' . Crud::maskId($id, 'news') . '/read', null, $this->tokenFor($user, ['news-api.list']))->assertStatus(403);
        $this->api('POST', 'api/v1/news/' . Crud::maskId($id, 'news') . '/read', null, $this->tokenFor($user, ['news-api.read']))->assertStatus(200);
    }

    public function testNoticesAreTheUsersReadableNoticesWithTheirReadState(): void
    {
        $user    = $this->createUser('ana@example.com', 'user');
        $read    = $this->newsPost('Avís llegit', 'private', [], 'published', 'notice');
        $this->newsPost('Avís nou', 'private', [], 'published', 'notice');
        $this->newsPost('Avís editors', 'groups', ['editor'], 'published', 'notice');
        $this->newsPost('Article', 'private');
        $token = $this->tokenFor($user);
        $this->api('POST', 'api/v1/news/' . Crud::maskId($read, 'news') . '/read', null, $token)->assertStatus(200);

        $all = $this->json($this->api('GET', 'api/v1/news/notices', null, $token))['data'];
        $titles = array_column($all, 'read', 'title');
        ksort($titles);
        $this->assertSame(['Avís llegit' => true, 'Avís nou' => false], $titles);
        $this->assertSame(Crud::maskId($read, 'news'), array_column($all, 'id', 'title')['Avís llegit']);

        $unread = $this->json($this->api('GET', 'api/v1/news/notices?unread=1', null, $token))['data'];
        $this->assertSame(['Avís nou'], array_column($unread, 'title'));

        $this->api('GET', 'api/v1/news/notices', null, $this->tokenFor($user, ['activity-mine.list']))->assertStatus(403);
    }

    /**
     * @param list<string> $groups
     */
    private function newsPost(string $title, string $visibility, array $groups = [], string $status = 'published', string $type = 'article'): int
    {
        $db = db_connect();
        $db->table('news')->insert([
            'slug' => url_title(strtolower($title), '-', true), 'type' => $type, 'priority' => 'info', 'pinned' => 0,
            'visibility' => $visibility, 'status' => $status, 'published_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
        $id = (int) $db->insertID();
        $db->table('news_translations')->insert(['news_id' => $id, 'locale' => 'ca', 'title' => $title, 'summary' => '', 'body' => '<p>' . $title . '</p>']);

        foreach ($groups as $group) {
            $row = $db->table('groups')->where('name', $group)->get()->getRowArray();
            $db->table('news_groups')->insert(['news_id' => $id, 'group_id' => (int) ($row['id'] ?? 0)]);
        }

        return $id;
    }
}
