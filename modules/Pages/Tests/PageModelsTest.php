<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use Modules\Core\Cache\CoreCache;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\PageModelRegistry;
use Modules\Core\Sections\SectionForm;
use Modules\Core\Sections\SectionRegistry;
use Modules\Pages\Config\Module;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;

/**
 * "New page from a model" (D-47-01, D-47-02): the gallery and its permission, the pages the two
 * generic models create (draft, `sections` layout, every section valid for its type's form,
 * placeholders in every language, images stored on the public disk) and that the shipped models,
 * their texts and the demo data carry no institution-specific content.
 *
 * @internal
 */
final class PageModelsTest extends PagesTestCase
{
    /** Words that must never appear in the shipped models and demo data (D-47-01). */
    private const FORBIDDEN = [
        'feder', 'fse+', 'fons social', 'fondo social', 'social fund', 'next generation', 'nextgeneration',
        'erasmus', 'prtr', 'unió europea', 'unión europea', 'european union', 'cofinan', 'co-fin',
        'finançat', 'financiado', 'funded by', 'subvenci', 'generalitat', 'ministerio', 'ministeri',
        'departament d\'educació', 'departament d’educació', 'conselleria',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $registry = new DeclarationRegistry();
        $registry->store($registry->build(['Core' => new \Modules\Core\Config\Module(), 'Pages' => new Module(), 'News' => new \Modules\News\Config\Module()]));
        (new CoreCache())->invalidate('modules');
    }

    public function testTheGalleryNeedsPagesAdd(): void
    {
        $this->loginWith('pages.list', 'pages.edit');
        $this->assertSame(403, $this->send('GET', 'backend/pages/models')->response()->getStatusCode());
        $this->assertSame(403, $this->send('POST', 'backend/pages/models/create', ['model' => 'pages.school'])->response()->getStatusCode(), 'A forged POST.');
        $this->assertSame(0, db_connect()->table('pages')->countAllResults());
    }

    public function testTheModelsAreOffered(): void
    {
        $models = (new PageModelRegistry())->all();
        $this->assertArrayHasKey('pages.school', $models);
        $this->assertArrayHasKey('pages.company', $models);

        $this->editor();
        $html = $this->body('GET', 'backend/pages/models');
        $this->assertStringContainsString(lang('Pages.models.school.label'), $html);
        $this->assertStringContainsString(lang('Pages.models.company.label'), $html);
        $this->assertNotFound('POST', 'backend/pages/models/create', ['model' => 'evil.model']);
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function models(): iterable
    {
        yield 'school' => ['pages.school', ['pages.carousel', 'pages.links', 'pages.links', 'news.list', 'pages.text', 'pages.logos', 'pages.media_text']];
        yield 'company' => ['pages.company', ['pages.hero', 'pages.links', 'pages.cards', 'pages.testimonials', 'pages.media_text', 'pages.cta']];
    }

    /**
     * @param list<string> $types
     *
     * @dataProvider models
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function testAModelCreatesADraftPageWithValidSections(string $key, array $types): void
    {
        $this->editor();
        $before   = db_connect()->table('stored_files')->countAllResults();
        $response = $this->send('POST', 'backend/pages/models/create', ['model' => $key]);
        $response->assertRedirect();

        $page = db_connect()->table('pages')->orderBy('id', 'desc')->get(1)->getRowArray();
        $this->assertIsArray($page);
        $id = (int) $page['id'];
        $this->assertStringContainsString('backend/pages/' . $id . '/sections', $response->getRedirectUrl() ?? '');
        $this->assertSame('draft', $page['status']);
        $this->assertSame('sections', $page['layout']);
        $this->assertSame('page_from_model', db_connect()->table('activity_log')->orderBy('id', 'desc')->get(1)->getRowArray()['action'] ?? null);
        $this->assertSame('create', (new Revisions())->history($id)[0]['action']);
        $this->assertGreaterThan($before, db_connect()->table('stored_files')->countAllResults(), 'Placeholder images are stored.');

        // Each language has its title and slug.
        $titles = array_column(db_connect()->table('page_translations')->where('page_id', $id)->get()->getResultArray(), 'title', 'locale');
        ksort($titles);
        $this->assertSame(['ca', 'en', 'es'], array_keys(array_filter($titles, static fn ($t): bool => $t !== '')));
        $slugs = array_column(db_connect()->table('page_translations')->where('page_id', $id)->get()->getResultArray(), 'slug');
        $this->assertNotContains('', $slugs);

        $sections = (new PageSections())->all($id);
        $this->assertSame($types, array_map(static fn ($s): string => $s->type, $sections));

        // Every section is valid for its type's form and has translated placeholders.
        $registry = new SectionRegistry();

        foreach ($sections as $section) {
            $type = $registry->get($section->type);
            $this->assertNotNull($type);
            $form = new SectionForm($type, FieldContext::current('Pages', $type->fileKeys($section)));
            $form->validate($form->formValues($section), $section->options);

            $ca = json_encode($section->texts['ca'] ?? []);
            $es = json_encode($section->texts['es'] ?? []);
            $en = json_encode($section->texts['en'] ?? []);
            $this->assertNotSame($ca, $en, "{$section->type}: translated texts.");
            $this->assertNotSame($ca, $es, "{$section->type}: translated texts.");
        }

        // The draft can be previewed and its sections render.
        $this->assertSame(200, $this->send('GET', 'backend/pages/preview/' . $this->code($id))->response()->getStatusCode());
    }

    public function testShippedModelsAndDemoDataAreGeneric(): void
    {
        $files = [
            ...glob(ROOTPATH . 'modules/Pages/Models/*.php') ?: [],
            ROOTPATH . 'modules/Pages/Database/Seeds/PagesDemoSeeder.php',
            ROOTPATH . 'modules/Menus/Database/Seeds/MenusDemoSeeder.php',
        ];
        $texts = [];

        foreach ($files as $file) {
            $texts[$file] = (string) file_get_contents($file);
        }

        foreach (['ca', 'es', 'en'] as $locale) {
            $language = include ROOTPATH . 'modules/Pages/Language/' . $locale . '/Pages.php';
            $texts['models.' . $locale] = (string) json_encode([$language['models'], $language['blocks']], JSON_UNESCAPED_UNICODE);
        }

        foreach ($texts as $where => $text) {
            foreach (self::FORBIDDEN as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $text, "{$where} must stay generic (D-47-01).");
            }
        }
    }
}
