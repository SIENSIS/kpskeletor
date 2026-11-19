<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use Modules\Core\Tests\ContentLocalesTrait;

/**
 * Events follow the site's languages (session 37, docs/i18n.md "Content languages"): with a fourth
 * language and Spanish as the default, the event and category forms show four tabs with Spanish
 * required, a category's slug comes from its Spanish name and the calendar falls back to Spanish.
 *
 * @internal
 */
final class ContentLocalesTest extends EventsTestCase
{
    use ContentLocalesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useContentLocales();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
    }

    public function testTheEventAndCategoryFormsOfferEveryLanguageWithTheDefaultRequired(): void
    {
        $this->assertTranslationTabs($this->body('GET', 'backend/events/add'), 'title');
        $this->assertTranslationTabs($this->body('GET', 'backend/events/categories/add'), 'name');
    }

    public function testAnEventNeedsTheDefaultLanguageOnly(): void
    {
        $this->send('POST', 'backend/events/add', $this->form(['ca' => ['title' => 'Només català', 'description' => '']]));
        $this->assertSame(0, db_connect()->table('events')->countAllResults(), 'Without Spanish: refused.');

        $this->send('POST', 'backend/events/add', $this->form(['es' => ['title' => 'Solo español', 'description' => '']]))->assertRedirect();
        $this->assertSame(['es'], array_column(db_connect()->table('event_translations')->get()->getResultArray(), 'locale'), 'Without Catalan: accepted.');
    }

    public function testACategoryWithoutCatalanGetsItsSlugFromSpanish(): void
    {
        $this->send('POST', 'backend/events/categories/add', ['kp_tr' => ['ca' => ['name' => 'Cursos', 'description' => '']], 'position' => '1', 'active' => '1', 'color' => '#0d6efd']);
        $this->assertSame(0, db_connect()->table('event_categories')->countAllResults(), 'Without Spanish: refused.');

        $this->send('POST', 'backend/events/categories/add', ['kp_tr' => ['es' => ['name' => 'Formación Continua', 'description' => '']], 'position' => '1', 'active' => '1', 'color' => '#0d6efd'])->assertRedirect();
        $this->assertSame(1, db_connect()->table('event_categories')->where('slug', 'formacion-continua')->countAllResults());
    }

    public function testTheCalendarFallsBackToTheDefaultLanguage(): void
    {
        $id = $this->event('Títol català', ['visibility' => 'public']);
        db_connect()->table('event_translations')->insert(['event_id' => $id, 'locale' => 'es', 'title' => 'Título español', 'description' => '']);
        $this->send('GET', 'logout');

        $this->withHeaders(['Accept-Language' => 'fr']);
        $this->assertSame(['Título español'], $this->feedTitles('calendar/feed'), 'No French text: the default language, not Catalan.');
    }

    /**
     * @param array<string, array<string, string>> $texts
     *
     * @return array<string, mixed>
     */
    private function form(array $texts): array
    {
        return [
            'kp_tr'          => $texts,
            'starts_at'      => '2026-12-01T10:00',
            'ends_at'        => '2026-12-01T11:00',
            'all_day'        => '0',
            'status'         => 'confirmed',
            'visibility'     => 'public',
            'recur_interval' => '1',
        ];
    }
}
