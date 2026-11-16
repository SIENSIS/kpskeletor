<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use Kpcrudy\Crud;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;

/**
 * Public API resource `public/news` (D-26-01): public posts only, no token, GET only, the same
 * masked ids, rate-limited per IP.
 *
 * @internal
 */
final class PublicNewsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    public function testOnlyPublicPostsAreListedWithoutAToken(): void
    {
        $this->publicPost('Pública', 'public');
        $this->publicPost('Privada', 'private');
        $this->publicPost('Esborrany públic', 'public', 'draft');

        $response = $this->api('GET', 'api/v1/public/news');
        $response->assertStatus(200);
        $data = $this->json($response)['data'];

        $this->assertSame(['Pública'], array_column($data, 'title'));
        $this->assertStringEndsWith('/news/publica', (string) $data[0]['url']);
        $this->assertArrayNotHasKey('visibility', $data[0]);
    }

    public function testATokenDoesNotWidenThePublicEndpoint(): void
    {
        $user = $this->createUser('ana@example.com', 'admin');
        $this->publicPost('Privada', 'private');

        $this->assertSame([], $this->json($this->api('GET', 'api/v1/public/news', null, $this->tokenFor($user)))['data']);
    }

    public function testDetailsOfANonPublicPostAreNotFound(): void
    {
        $public  = $this->publicPost('Pública', 'public');
        $private = $this->publicPost('Privada', 'private');

        $ok = $this->api('GET', 'api/v1/public/news/' . Crud::maskId($public, 'news'));
        $ok->assertStatus(200);
        $this->assertSame('<p>Pública</p>', $this->json($ok)['data']['body']);

        $this->api('GET', 'api/v1/public/news/' . Crud::maskId($private, 'news'))->assertStatus(404);
    }

    public function testThereAreNoWriteRoutes(): void
    {
        $id = $this->publicPost('Pública', 'public');

        foreach ([['POST', 'api/v1/public/news'], ['PATCH', 'api/v1/public/news/' . Crud::maskId($id, 'news')], ['DELETE', 'api/v1/public/news/' . Crud::maskId($id, 'news')]] as [$method, $path]) {
            try {
                $status = $this->api($method, $path, $method === 'DELETE' ? null : ['title' => 'Nova'])->response()->getStatusCode();
            } catch (PageNotFoundException) {
                $status = 404;
            }

            $this->assertSame(404, $status, "{$method} {$path}");
        }

        $this->assertSame(1, db_connect()->table('news')->where('deleted_at', null)->countAllResults());
    }

    public function testRequestsArePerIpLimited(): void
    {
        config('Kpapy')->rateLimitPublic = 2;

        $this->api('GET', 'api/v1/public/news')->assertStatus(200);
        $this->api('GET', 'api/v1/public/news')->assertStatus(200);
        $limited = $this->api('GET', 'api/v1/public/news');
        $limited->assertStatus(429);
        $this->assertNotSame('', $limited->response()->getHeaderLine('Retry-After'));
    }

    private function publicPost(string $title, string $visibility, string $status = 'published'): int
    {
        $db = db_connect();
        $db->table('news')->insert([
            'slug' => strtr(mb_strtolower($title), ['ú' => 'u', ' ' => '-', 'à' => 'a']), 'type' => 'article', 'priority' => 'info', 'pinned' => 0,
            'visibility' => $visibility, 'status' => $status, 'published_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
        $id = (int) $db->insertID();
        $db->table('news_translations')->insert(['news_id' => $id, 'locale' => 'ca', 'title' => $title, 'summary' => '', 'body' => '<p>' . $title . '</p>']);

        return $id;
    }
}
