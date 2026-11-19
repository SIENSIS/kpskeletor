<?php

declare(strict_types=1);

namespace Modules\News\Tests;

/**
 * News follows the site's languages (session 37, docs/i18n.md "Content languages"): with a fourth
 * language and Spanish as the default, the forms show four tabs with Spanish required, the slug
 * comes from the Spanish title and readers fall back to Spanish.
 *
 * @internal
 */
final class ContentLocalesTest extends NewsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useContentLocales();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
    }

    public function testThePostAndCategoryFormsOfferEveryLanguageWithTheDefaultRequired(): void
    {
        $this->assertTranslationTabs($this->body('GET', 'backend/news/posts/add'), 'title');
        $this->assertTranslationTabs($this->body('GET', 'backend/news/categories/add'), 'name');
    }

    public function testThePostNeedsTheDefaultLanguageAndItsSlugComesFromIt(): void
    {
        $post = ['type' => 'article', 'priority' => 'info', 'visibility' => 'public'];

        $this->send('POST', 'backend/news/posts/add', $post + ['kp_tr' => ['ca' => ['title' => 'Només en català', 'summary' => '', 'body' => '']]]);
        $this->assertSame(0, db_connect()->table('news')->countAllResults(), 'Without Spanish: refused.');

        $this->send('POST', 'backend/news/posts/add', $post + ['kp_tr' => [
            'es' => ['title' => 'Título en español', 'summary' => '', 'body' => ''],
            'fr' => ['title' => 'Titre en français', 'summary' => '', 'body' => ''],
        ]])->assertRedirect();

        $row = db_connect()->table('news')->get()->getRowArray();
        $this->assertIsArray($row, 'Without Catalan: accepted.');
        $this->assertSame('titulo-en-espanol', $row['slug']);
        $this->assertSame(['es', 'fr'], array_column(db_connect()->table('news_translations')->where('news_id', $row['id'])->orderBy('locale')->get()->getResultArray(), 'locale'));
    }

    public function testACategoryWithoutCatalanGetsItsSlugFromSpanish(): void
    {
        $this->send('POST', 'backend/news/categories/add', ['kp_tr' => ['es' => ['name' => 'Ámbito Social', 'description' => '']], 'position' => '1', 'active' => '1'])->assertRedirect();

        $this->assertSame(1, db_connect()->table('news_categories')->where('slug', 'ambito-social')->countAllResults());
    }

    public function testReadersFallBackToTheDefaultLanguage(): void
    {
        $id = $this->newsPost('Text català', ['visibility' => 'public', 'slug' => 'noticia']);
        db_connect()->table('news_translations')->insert(['news_id' => $id, 'locale' => 'es', 'title' => 'Texto español', 'summary' => '', 'body' => '<p>Cuerpo</p>']);

        $page = (string) $this->send('GET', 'fr/news/noticia')->response()->getBody();
        $this->assertStringContainsString('<html lang="fr"', $page);
        $this->assertStringContainsString('Texto español', $page, 'No French text: the default language, not Catalan.');
        $this->assertStringNotContainsString('Text català', $page);

        db_connect()->table('news_translations')->insert(['news_id' => $id, 'locale' => 'fr', 'title' => 'Texte français', 'summary' => '', 'body' => '<p>Corps</p>']);
        $page = (string) $this->send('GET', 'fr/news/noticia')->response()->getBody();
        $this->assertStringContainsString('Texte français', $page);
    }

    public function testALanguageNoLongerOfferedKeepsItsRowsButIsNotShown(): void
    {
        $id = $this->newsPost('Text català', ['visibility' => 'public', 'slug' => 'noticia']);
        db_connect()->table('news_translations')->insert(['news_id' => $id, 'locale' => 'es', 'title' => 'Texto español', 'summary' => '', 'body' => '']);
        setting('Core.locales', ['es', 'en', 'fr']);

        $page = (string) $this->send('GET', 'news/noticia')->response()->getBody(); // the default language (es)
        $this->assertStringContainsString('Texto español', $page);
        $this->assertStringNotContainsString('Text català', $page);

        $form = $this->body('GET', 'backend/news/posts/edit/' . $this->code($id));
        $this->assertStringNotContainsString('kp_tr[ca]', $form, 'Catalan is no longer edited.');

        $this->send('POST', 'backend/news/posts/edit/' . $this->code($id), [
            'kp_tr' => ['es' => ['title' => 'Texto cambiado', 'summary' => '', 'body' => '']],
            'type'  => 'article', 'priority' => 'info', 'visibility' => 'public',
        ])->assertRedirect();
        $this->assertSame(1, db_connect()->table('news_translations')->where('news_id', $id)->where('locale', 'ca')->countAllResults(), 'Its rows are kept.');
    }
}
