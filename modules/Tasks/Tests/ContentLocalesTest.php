<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

/**
 * Projects follow the site's languages (session 37, docs/i18n.md "Content languages"): with a
 * fourth language and Spanish as the default, the project form shows four tabs with Spanish
 * required, the slug comes from the Spanish name and the public page falls back to Spanish.
 *
 * @internal
 */
final class ContentLocalesTest extends TasksTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useContentLocales();
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
    }

    public function testTheProjectFormOffersEveryLanguageWithTheDefaultRequired(): void
    {
        $this->assertTranslationTabs($this->body('GET', 'backend/tasks/projects/add'), 'name');
    }

    public function testAProjectNeedsTheDefaultLanguageAndItsSlugComesFromIt(): void
    {
        $form = ['color' => '#0d6efd', 'active' => '1', 'ends_on' => '', 'slug' => ''];

        $this->send('POST', 'backend/tasks/projects/add', $form + ['kp_tr' => ['ca' => ['name' => 'Només català', 'description' => '']]]);
        $this->assertSame(0, db_connect()->table('task_projects')->countAllResults(), 'Without Spanish: refused.');

        $this->send('POST', 'backend/tasks/projects/add', $form + ['kp_tr' => ['es' => ['name' => 'Página nueva', 'description' => '']]])->assertRedirect();
        $this->assertSame(1, db_connect()->table('task_projects')->where('slug', 'pagina-nueva')->countAllResults(), 'Without Catalan: accepted, slug from Spanish.');
    }

    public function testThePublicPageFallsBackToTheDefaultLanguage(): void
    {
        $web = $this->project('Nou web');
        db_connect()->table('task_project_translations')->insert(['project_id' => $web, 'locale' => 'es', 'name' => 'Nueva web', 'description' => '']);
        $this->task('Pública del web', ['visibility' => 'public', 'project_id' => $web]);

        $page = html_entity_decode((string) $this->withHeaders(['Accept-Language' => 'fr'])->send('GET', 'tasks/project/nou-web')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Nueva web', $page, 'No French text: the default language, not Catalan.');
        $this->assertStringNotContainsString('Nou web', $page);
    }
}
