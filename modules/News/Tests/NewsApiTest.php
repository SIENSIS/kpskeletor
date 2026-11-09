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

        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['news-read.list']))->assertStatus(200);
        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['news-read.*']))->assertStatus(200);
        $this->api('GET', 'api/v1/news', null, $this->tokenFor($user, ['activity-mine.list']))->assertStatus(403);
    }

    /**
     * @param list<string> $groups
     */
    private function newsPost(string $title, string $visibility, array $groups = [], string $status = 'published'): int
    {
        $db = db_connect();
        $db->table('news')->insert([
            'slug' => url_title(strtolower($title), '-', true), 'type' => 'article', 'priority' => 'info', 'pinned' => 0,
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
