<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use Kpcrudy\Crud;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Tests\ContentLocalesTrait;

/**
 * The news APIs follow the site's languages (session 37): a fourth language is answered with the
 * fallback to the site's default language (Spanish here), not to Catalan.
 *
 * @internal
 */
final class ContentLocalesApiTest extends ApiTestCase
{
    use ContentLocalesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
        $this->useContentLocales();
    }

    public function testTheApisAnswerInTheNewLanguageWithTheDefaultFallback(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $db   = db_connect();
        $db->table('news')->insert(['slug' => 'noticia', 'type' => 'article', 'priority' => 'info', 'pinned' => 0, 'visibility' => 'public', 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() - 3600)]);
        $id = (int) $db->insertID();
        $db->table('news_translations')->insertBatch([
            ['news_id' => $id, 'locale' => 'ca', 'title' => 'Títol', 'summary' => 'Resum', 'body' => '<p>Cos</p>'],
            ['news_id' => $id, 'locale' => 'es', 'title' => 'Título', 'summary' => 'Resumen', 'body' => '<p>Cuerpo</p>'],
            ['news_id' => $id, 'locale' => 'fr', 'title' => 'Titre', 'summary' => '', 'body' => ''],
        ]);
        $code = Crud::maskId($id, 'news');

        $private = $this->json($this->api('GET', 'api/v1/news/' . $code . '?lang=fr', null, $this->tokenFor($user)))['data'];
        $this->assertSame('Titre', $private['title']);
        $this->assertSame('Resumen', $private['summary'], 'An empty French field: the default language, not Catalan.');

        $public = $this->json($this->api('GET', 'api/v1/public/news/' . $code, null, null, ['Accept-Language' => 'fr']))['data'];
        $this->assertSame('Titre', $public['title']);
        $this->assertSame('<p>Cuerpo</p>', $public['body']);

        $all = $this->json($this->api('GET', 'api/v1/public/news/' . $code . '?lang=*'))['data'];
        $this->assertSame(['ca', 'es', 'en', 'fr'], array_keys($all['title']), 'Every site language.');
    }
}
