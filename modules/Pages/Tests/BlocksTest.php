<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use Kpcrudy\Crud;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Sections\SectionData;
use Modules\Core\Theme\ZoneContent;
use Modules\Pages\Config\Module;
use Modules\Pages\Services\BlockRevisions;
use Modules\Pages\Services\BlockZones;
use Modules\Pages\Services\CurrentPage;
use Modules\Pages\Services\PageBlocks;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;

/**
 * Reusable blocks (D-47-01, D-47-02): the CRUD and its permissions, the block's section editor
 * (no nested blocks, forged posts), revisions, the block section inside a page, theme zones and
 * every visibility rule, the visitor's language with fallback, drafts, and deleting a block in
 * use (refused by the plain delete, confirmed by "Delete with its uses").
 *
 * @internal
 */
final class BlocksTest extends PagesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $registry = new DeclarationRegistry();
        $registry->store($registry->build(['Pages' => new Module()]));
        (new CoreCache())->invalidate('modules');
        CurrentPage::reset();
    }

    protected function tearDown(): void
    {
        CurrentPage::reset();
        parent::tearDown();
    }

    /**
     * A published block with one text section (Catalan, optionally Spanish).
     */
    private function block(string $name, string $ca, ?string $es = null, string $status = 'published'): int
    {
        $texts = ['ca' => ['heading' => '', 'text' => '<p>' . $ca . '</p>']];

        if ($es !== null) {
            $texts['es'] = ['heading' => '', 'text' => '<p>' . $es . '</p>'];
        }

        return (new PageBlocks())->create($name, [new SectionData('pages.text', ['column' => 'narrow'], $texts)], $status);
    }

    private function blockSection(int $pageId, int $blockId, bool $hidden = false): int
    {
        return (new PageSections())->save($pageId, new SectionData('pages.block', ['block' => (string) $blockId], [], [], $hidden));
    }

    private function sectionsPage(string $title): int
    {
        $id = $this->page($title, ['layout' => 'sections']);
        (new PageSections())->save($id, new SectionData('pages.text', ['column' => 'narrow'], ['ca' => ['heading' => '', 'text' => '<p>Contingut de ' . $title . '</p>']]));

        return $id;
    }

    private function blockCode(int $id): string
    {
        return Crud::maskId($id, 'page_blocks');
    }

    public function testTheBlocksScreenNeedsItsPermissions(): void
    {
        $block = $this->block('Logotips', 'Hola');

        $this->assertTrue($this->send('GET', 'backend/pages/blocks')->isRedirect(), 'Guests go to the login.');

        $this->loginWith('pages.list', 'pages.edit');
        $this->assertSame(403, $this->send('GET', 'backend/pages/blocks')->response()->getStatusCode());
        $this->assertSame(403, $this->send('GET', 'backend/pages/blocks/' . $block . '/sections')->response()->getStatusCode());
        $this->assertSame(403, $this->send('POST', 'backend/pages/blocks/' . $block . '/sections/save', ['type' => 'pages.text', 'f' => ['text' => ['ca' => '<p>x</p>']]])->response()->getStatusCode(), 'A forged POST.');
        $this->assertSame(403, $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.before_footer', 'rule' => 'all'])->response()->getStatusCode());
        $this->assertSame(1, PageSections::forBlocks()->count($block));
        $this->assertSame([], (new BlockZones())->forBlock($block));
    }

    public function testAnEditorCreatesABlockAndEditsItsSections(): void
    {
        $this->editor();

        $this->assertSame(200, $this->send('GET', 'backend/pages/blocks')->response()->getStatusCode());
        $this->send('POST', 'backend/pages/blocks/add', ['name' => 'Avís', 'status' => 'published'])->assertRedirect();
        $row = db_connect()->table('page_blocks')->where('name', 'Avís')->get()->getRowArray();
        $this->assertIsArray($row);
        $id = (int) $row['id'];
        $this->assertSame('create', (new BlockRevisions())->history($id)[0]['action']);

        // The gallery does not offer the block section inside a block, and a forged post is refused.
        $gallery = $this->body('GET', 'backend/pages/blocks/' . $id . '/sections/add');
        $this->assertStringContainsString('pages.cta', $gallery);
        $this->assertStringNotContainsString('pages.block', $gallery);
        $this->assertNotFound('GET', 'backend/pages/blocks/' . $id . '/sections/new/pages.block');
        $this->assertNotFound('POST', 'backend/pages/blocks/' . $id . '/sections/save', ['type' => 'pages.block', 'f' => ['block' => (string) $id]]);

        $this->send('POST', 'backend/pages/blocks/' . $id . '/sections/save', ['type' => 'pages.text', 'f' => ['text' => ['ca' => '<p>Obert a l’agost</p>'], 'column' => 'narrow']])->assertRedirect();
        $sections = PageSections::forBlocks()->all($id);
        $this->assertCount(1, $sections);
        $this->assertSame([], (new PageSections())->all($id), 'Block sections are not page sections.');
        $this->assertSame('block_section_added', db_connect()->table('activity_log')->orderBy('id', 'desc')->get(1)->getRowArray()['action'] ?? null);
        $this->assertContains('sections', (new BlockRevisions())->history($id)[0]['changes']);

        // The section of another block cannot be reached through this one.
        $other = $this->block('Un altre', 'Altre');
        $this->assertNotFound('GET', 'backend/pages/blocks/' . $other . '/sections/' . (int) $sections[0]->id);
        $this->send('POST', 'backend/pages/blocks/' . $other . '/sections/' . (int) $sections[0]->id . '/delete');
        $this->assertSame(1, PageSections::forBlocks()->count($id));
    }

    public function testBlockRevisionsRestoreTheSections(): void
    {
        $id = $this->block('Avís', 'Primera versió');
        (new BlockRevisions())->record($id, null, 'create');
        $section = PageSections::forBlocks()->all($id)[0];
        PageSections::forBlocks()->save($id, new SectionData('pages.text', ['column' => 'narrow'], ['ca' => ['heading' => '', 'text' => '<p>Segona versió</p>']]), (int) $section->id);
        (new BlockRevisions())->record($id, null);
        $history = (new BlockRevisions())->history($id);
        $this->assertCount(2, $history);
        $this->assertArrayHasKey('sections', (new BlockRevisions())->diff($id, $history[0]['id']));

        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->assertStringContainsString('Segona versió', $this->body('GET', 'backend/pages/blocks/revisions/' . $this->blockCode($id)));
        $this->send('POST', 'backend/pages/blocks/restorerevision/' . $this->blockCode($id), ['revision' => (string) $history[1]['id']])->assertRedirect();

        $this->assertStringContainsString('Primera versió', (string) PageSections::forBlocks()->all($id)[0]->texts['ca']['text']);
        $this->assertSame('restore', (new BlockRevisions())->history($id)[0]['action']);
    }

    public function testABlockSectionShowsTheBlockInsideAPage(): void
    {
        $block = $this->block('Horari', 'Obert de 9 a 14', 'Abierto de 9 a 14');
        $page  = $this->sectionsPage('Portada');
        $this->blockSection($page, $block);

        $html = $this->body('GET', 'portada');
        $this->assertStringContainsString('Contingut de Portada', $html);
        $this->assertStringContainsString('Obert de 9 a 14', $html);
        $this->assertStringContainsString('data-section-type="pages.text"', $html);
        $this->assertStringNotContainsString('data-section-type="pages.block"', $html, 'Replaced by the block\'s own sections.');

        // The visitor's language; a language without text falls back to the default one.
        $this->assertStringContainsString('Abierto de 9 a 14', $this->body('GET', 'es/portada'));
        $this->assertStringContainsString('Obert de 9 a 14', $this->body('GET', 'en/portada'));

        // Changing the block changes the page.
        $section = PageSections::forBlocks()->all($block)[0];
        PageSections::forBlocks()->save($block, new SectionData('pages.text', ['column' => 'narrow'], ['ca' => ['heading' => '', 'text' => '<p>Tancat per vacances</p>']]), (int) $section->id);
        $this->assertStringContainsString('Tancat per vacances', $this->body('GET', 'portada'));

        // A hidden block section, a hidden section inside the block and a draft block show nothing.
        PageSections::forBlocks()->setHidden($block, (int) $section->id, true);
        $this->assertStringNotContainsString('Tancat per vacances', $this->body('GET', 'portada'));
        PageSections::forBlocks()->setHidden($block, (int) $section->id, false);
        db_connect()->table('page_blocks')->where('id', $block)->update(['status' => 'draft']);
        $this->assertStringNotContainsString('Tancat per vacances', $this->body('GET', 'portada'));
    }

    public function testZonesFollowTheirRules(): void
    {
        $home  = $this->sectionsPage('Inici');
        $about = $this->sectionsPage('Qui som');
        $other = $this->sectionsPage('Contacte');
        setting('Pages.homePage', (string) $home);
        $zones = new BlockZones();

        $all    = $this->block('Tots', 'BLOC-TOTS');
        $onHome = $this->block('Inici', 'BLOC-INICI');
        $only   = $this->block('Només', 'BLOC-NOMES');
        $except = $this->block('Excepte', 'BLOC-EXCEPTE');
        $top    = $this->block('Dalt', 'BLOC-DALT');
        $zones->save($all, 'public.before_footer', 'all', [], 20);
        $zones->save($onHome, 'public.before_footer', 'home', [], 10);
        $zones->save($only, 'public.before_footer', 'only', [$about], 30);
        $zones->save($except, 'public.before_footer', 'except', [$about], 40);
        $zones->save($top, 'public.after_header', 'all', [], 0);

        $html = $this->body('GET', '/');
        $this->assertStringContainsString('BLOC-TOTS', $html);
        $this->assertStringContainsString('BLOC-INICI', $html);
        $this->assertStringNotContainsString('BLOC-NOMES', $html);
        $this->assertStringContainsString('BLOC-EXCEPTE', $html);
        $this->assertLessThan(strpos($html, 'BLOC-TOTS'), strpos($html, 'BLOC-INICI'), 'Ordered by position.');
        $this->assertLessThan(strpos($html, 'Contingut de Inici'), strpos($html, 'BLOC-DALT'), 'after_header comes first.');
        $this->assertGreaterThan(strpos($html, 'Contingut de Inici'), strpos($html, 'BLOC-TOTS'), 'before_footer comes after the content.');
        $this->assertLessThan(strpos($html, '<footer'), strpos($html, 'BLOC-TOTS'), 'Inside <main>, before the footer.');

        CurrentPage::reset();
        $html = $this->body('GET', 'qui-som');
        $this->assertStringContainsString('BLOC-TOTS', $html);
        $this->assertStringNotContainsString('BLOC-INICI', $html);
        $this->assertStringContainsString('BLOC-NOMES', $html);
        $this->assertStringNotContainsString('BLOC-EXCEPTE', $html);

        CurrentPage::reset();
        $html = $this->body('GET', 'contacte');
        $this->assertStringNotContainsString('BLOC-NOMES', $html);
        $this->assertStringContainsString('BLOC-EXCEPTE', $html);
        $this->assertNotSame('', $other);

        // A draft block is shown nowhere.
        db_connect()->table('page_blocks')->where('id', $all)->update(['status' => 'draft']);
        CurrentPage::reset();
        $this->assertStringNotContainsString('BLOC-TOTS', $this->body('GET', 'qui-som'));
    }

    public function testTheRulesOnOtherScreens(): void
    {
        $this->assertTrue(BlockZones::applies('all', [], null, false));
        $this->assertFalse(BlockZones::applies('home', [], null, false));
        $this->assertTrue(BlockZones::applies('home', [], null, true));
        $this->assertFalse(BlockZones::applies('only', [3], null, false), 'Another module\'s screen is not a chosen page.');
        $this->assertTrue(BlockZones::applies('except', [3], null, false));
        $this->assertFalse(BlockZones::applies('except', [3], 3, false));
        $this->assertFalse(BlockZones::applies('evil', [], 1, true));

        // A zone the theme does not declare stays empty.
        $block = $this->block('Tots', 'BLOC-TOTS');
        db_connect()->table('page_block_zones')->insert(['block_id' => $block, 'zone' => 'public.nowhere', 'rule' => 'all', 'pages' => '[]', 'position' => 0]);
        $this->assertSame('', service('theme')->zone('public.nowhere', 'public'));
        $content = new ZoneContent('public.nowhere');
        (new \Modules\Pages\Services\BlockRenderer())->fillZone($content);
        $this->assertStringContainsString('BLOC-TOTS', $content->render(), 'The listener fills it; the theme decides whether it is printed.');
    }

    public function testPlacementsAreValidated(): void
    {
        $block = $this->block('Avís', 'Text');
        $page  = $this->page('Qui som');
        $this->editor();

        $this->assertStringContainsString('public.before_footer', $this->body('GET', 'backend/pages/blocks/' . $block . '/zones'));
        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.evil', 'rule' => 'all', 'position' => '1']);
        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.before_footer', 'rule' => 'evil', 'position' => '1']);
        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.before_footer', 'rule' => 'only', 'position' => '1']);
        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.before_footer', 'rule' => 'only', 'pages' => ['999'], 'position' => '1']);
        $this->assertCount(0, (new BlockZones())->forBlock($block));

        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/save', ['zone' => 'public.before_footer', 'rule' => 'only', 'pages' => [(string) $page], 'position' => '5'])->assertRedirect();
        $placements = (new BlockZones())->forBlock($block);
        $this->assertCount(1, $placements);
        $this->assertSame([$page], $placements[0]['pages']);
        $this->assertSame('block_placed', db_connect()->table('activity_log')->orderBy('id', 'desc')->get(1)->getRowArray()['action'] ?? null);

        // Another block's placement cannot be changed or removed through this one.
        $other = $this->block('Altre', 'Text');
        $this->send('POST', 'backend/pages/blocks/' . $other . '/zones/' . $placements[0]['id'] . '/delete');
        $this->send('POST', 'backend/pages/blocks/' . $other . '/zones/save', ['id' => (string) $placements[0]['id'], 'zone' => 'public.after_header', 'rule' => 'all']);
        $this->assertSame('public.before_footer', (new BlockZones())->forBlock($block)[0]['zone']);
        $this->assertSame([], (new BlockZones())->forBlock($other));

        $this->send('POST', 'backend/pages/blocks/' . $block . '/zones/' . $placements[0]['id'] . '/delete')->assertRedirect();
        $this->assertSame([], (new BlockZones())->forBlock($block));
    }

    public function testDeletingABlockInUseNeedsConfirmationAndRemovesItsUses(): void
    {
        $block = $this->block('Logotips', 'Logos');
        $page  = $this->sectionsPage('Portada');
        $this->blockSection($page, $block);
        (new BlockZones())->save($block, 'public.before_footer', 'all', [], 0);
        $unused = $this->block('Sense ús', 'Res');
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->assertSame(['zones' => 1, 'pages' => 1], array_map('count', (new PageBlocks())->uses($block)));

        // The plain delete refuses a block in use.
        $this->send('POST', 'backend/pages/blocks/delete/' . $this->blockCode($block));
        $this->assertNotNull((new PageBlocks())->find($block));

        // "Delete with its uses" (confirmed in the browser) removes it everywhere.
        $this->send('POST', 'backend/pages/blocks/deletewithuses/' . $this->blockCode($block))->assertRedirect();
        $this->assertNull((new PageBlocks())->find($block));
        $this->assertSame(0, db_connect()->table('page_block_zones')->where('block_id', $block)->countAllResults());
        $this->assertSame(0, PageSections::forBlocks()->count($block));
        $this->assertSame(['pages.text'], array_map(static fn (SectionData $s): string => $s->type, (new PageSections())->all($page)));
        $this->assertNotEmpty((new Revisions())->history($page), 'The page touched keeps a revision.');
        $this->assertSame('deletewithuses', db_connect()->table('activity_log')->where('action', 'deletewithuses')->get(1)->getRowArray()['action'] ?? null);

        // A block without uses is deleted by the plain delete.
        $this->send('POST', 'backend/pages/blocks/delete/' . $this->blockCode($unused))->assertRedirect();
        $this->assertNull((new PageBlocks())->find($unused));
    }

    public function testAnEditorCannotDeleteBlocks(): void
    {
        $block = $this->block('Logotips', 'Logos');
        $this->editor();

        $this->send('POST', 'backend/pages/blocks/delete/' . $this->blockCode($block));
        $this->send('POST', 'backend/pages/blocks/deletewithuses/' . $this->blockCode($block));
        $this->assertNotNull((new PageBlocks())->find($block));
    }

    public function testTheBlockSectionWarnsAboutDraftsAndMissingBlocks(): void
    {
        $block = $this->block('Esborrany', 'Text', null, 'draft');
        $page  = $this->sectionsPage('Portada');
        $this->blockSection($page, $block);
        $this->blockSection($page, 9999);
        $this->editor();

        $html = $this->body('GET', 'backend/pages/' . $page . '/sections');
        $this->assertStringContainsString(lang('Pages.blocks.warnings.draft', ['Esborrany']), $html);
        $this->assertStringContainsString(lang('Pages.blocks.warnings.missing'), $html);

        // A forged block id is refused by the section's form.
        $response = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'pages.block', 'f' => ['block' => '9999']]);
        $this->assertSame(422, $response->response()->getStatusCode());
    }
}
