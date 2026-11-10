<?php

declare(strict_types=1);

namespace Modules\News\Tests;

/**
 * The public site and the RSS feed show only public, published, not expired posts, whoever is
 * signed in; anything else is a 404.
 *
 * @internal
 */
final class PublicSiteTest extends NewsTestCase
{
    public function testTheListShowsOnlyPublicPosts(): void
    {
        $this->newsPost('Notícia pública', ['visibility' => 'public']);
        $this->newsPost('Notícia privada');
        $this->newsPost('Notícia de grup', ['visibility' => 'groups'], ['user']);
        $this->newsPost('Pública en esborrany', ['visibility' => 'public', 'status' => 'draft']);

        foreach ([false, true] as $signedIn) {
            if ($signedIn) {
                $this->createUser('ana@example.com', 'user');
                $this->login('ana@example.com');
            }

            $page = $this->body('GET', 'news');
            $this->assertStringContainsString('Notícia pública', $page);
            $this->assertStringNotContainsString('Notícia privada', $page);
            $this->assertStringNotContainsString('Notícia de grup', $page);
            $this->assertStringNotContainsString('Pública en esborrany', $page);
        }
    }

    public function testDetailsOfNonPublicPostsAreNotFound(): void
    {
        $this->newsPost('Oberta', ['visibility' => 'public', 'slug' => 'oberta']);
        $this->newsPost('Tancada', ['slug' => 'tancada']);

        $this->assertStringContainsString('Text de Oberta', $this->body('GET', 'news/oberta'));
        $this->assertNotFound('GET', 'news/tancada');
        $this->assertNotFound('GET', 'news/no-existeix');
    }

    public function testCategoriesAndTheFeed(): void
    {
        db_connect()->table('news_categories')->insert(['slug' => 'activitats', 'position' => 1, 'active' => 1]);
        $category = (int) db_connect()->insertID();
        db_connect()->table('news_category_translations')->insert(['category_id' => $category, 'locale' => 'ca', 'name' => 'Activitats']);
        $this->newsPost('Amb categoria', ['visibility' => 'public', 'category_id' => $category]);
        $this->newsPost('Sense categoria', ['visibility' => 'public']);
        $this->newsPost('Privada amb categoria', ['category_id' => $category]);

        $page = $this->body('GET', 'news/category/activitats');
        $this->assertStringContainsString('Amb categoria', $page);
        $this->assertStringNotContainsString('Sense categoria', $page);
        $this->assertStringNotContainsString('Privada amb categoria', $page);
        $this->assertNotFound('GET', 'news/category/no-existeix');

        $response = $this->send('GET', 'news/feed.xml');
        $feed     = (string) $response->response()->getBody();
        $this->assertStringStartsWith('<?xml', $feed);
        $this->assertStringContainsString('application/rss+xml', $response->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Amb categoria', $feed);
        $this->assertStringNotContainsString('Privada', $feed);
        $this->assertNotFalse(simplexml_load_string($feed), 'Well-formed XML.');
    }
}
