<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

/**
 * Pages follow the site's languages (session 37, docs/i18n.md "Content languages"): with a fourth
 * language and Spanish as the default, the form shows four tabs with Spanish required, a page
 * without Catalan is accepted (its slug from the Spanish title) and readers fall back to Spanish.
 *
 * @internal
 */
final class ContentLocalesTest extends PagesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useContentLocales();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
    }

    public function testTheFormOffersEveryLanguageWithTheDefaultRequired(): void
    {
        $this->assertTranslationTabs($this->body('GET', 'backend/pages/add'), 'title');
    }

    public function testAPageNeedsTheDefaultLanguageOnly(): void
    {
        $empty = ['slug' => '', 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => ''];

        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Només català']));
        $this->assertSame(0, db_connect()->table('pages')->countAllResults(), 'Without Spanish: refused.');

        $form          = $this->form(['title' => '']);
        $form['kp_tr'] = ['es' => ['title' => 'Quiénes somos'] + $empty];
        $this->send('POST', 'backend/pages/add', $form)->assertRedirect();

        $page = db_connect()->table('pages')->get()->getRowArray();
        $this->assertIsArray($page, 'Without Catalan: accepted.');
        $this->assertSame('quienes-somos', $this->text((int) $page['id'], 'es')['slug']);
        $this->assertSame(0, db_connect()->table('page_translations')->where('locale', 'ca')->countAllResults());
    }

    public function testReadersFallBackToTheDefaultLanguage(): void
    {
        $this->page('Qui som', [], ['es' => ['title' => 'Quiénes somos', 'slug' => 'quienes-somos']]);

        $page = $this->body('GET', 'fr/quienes-somos'); // no French slug: the default language's
        $this->assertStringContainsString('Text de Quiénes somos', $page, 'No French text: the default language, not Catalan.');
        $this->assertStringNotContainsString('Text de Qui som', $page);
    }
}
