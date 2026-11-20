<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use Modules\Core\Cache\CoreCache;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Sections\SectionData;
use Modules\Pages\Config\Module;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;

/**
 * Pages built from sections (D-45-01): the editor at `backend/pages/<id>/sections` (permissions
 * and forged posts), ordering, hide, duplicate and delete, revisions that restore sections,
 * switching the layout, and the public rendering of each type in the full-width layout.
 *
 * @internal
 */
final class PageSectionsTest extends PagesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $registry = new DeclarationRegistry();
        $registry->store($registry->build(['Pages' => new Module()]));
        (new CoreCache())->invalidate('modules');
    }

    /**
     * @param array<string, mixed>                $values
     * @param array<string, array<string, mixed>> $texts
     * @param array<string, mixed>                $options
     */
    private function section(int $pageId, string $type, array $values, array $texts, array $options = [], bool $hidden = false): int
    {
        return (new PageSections())->save($pageId, new SectionData($type, $values, $texts, $options, $hidden));
    }

    private function textSection(int $pageId, string $text, bool $hidden = false): int
    {
        return $this->section($pageId, 'pages.text', ['column' => 'narrow'], ['ca' => ['heading' => '', 'text' => '<p>' . $text . '</p>']], [], $hidden);
    }

    private function found(int $pageId, int $sectionId): SectionData
    {
        $section = (new PageSections())->find($pageId, $sectionId);
        $this->assertNotNull($section, "Section {$sectionId} of page {$pageId}.");

        return $section;
    }

    /**
     * @return list<string> the Catalan texts of the page's sections, in order
     */
    private function order(int $pageId): array
    {
        return array_map(static fn (SectionData $s): string => strip_tags((string) ($s->texts['ca']['text'] ?? $s->texts['ca']['title'] ?? '')), (new PageSections())->all($pageId));
    }

    public function testTheEditorNeedsPagesEdit(): void
    {
        $page = $this->page('Portada', ['layout' => 'sections']);

        $this->assertTrue($this->send('GET', 'backend/pages/' . $page . '/sections')->isRedirect(), 'Guests go to the login.');

        $this->loginWith('pages.list', 'pages.show');
        $this->assertSame(403, $this->send('GET', 'backend/pages/' . $page . '/sections')->response()->getStatusCode());
        $forged = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'pages.text', 'f' => ['text' => ['ca' => '<p>x</p>']]]);
        $this->assertSame(403, $forged->response()->getStatusCode(), 'A forged POST without pages.edit.');
        $this->assertSame(0, (new PageSections())->count($page));
    }

    public function testAnEditorAddsEditsAndSeesErrors(): void
    {
        $page = $this->page('Portada', ['layout' => 'sections']);
        (new Revisions())->record($page, null, 'create');
        $this->editor();

        $this->assertStringContainsString('pages.hero', $this->body('GET', 'backend/pages/' . $page . '/sections/add'));
        $this->assertStringContainsString('name="f[title][ca]"', $this->body('GET', 'backend/pages/' . $page . '/sections/new/pages.cta'));

        $response = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'pages.cta', 'f' => ['title' => ['ca' => 'Vine!'], 'text' => ['ca' => 'Text'], 'link' => ['type' => 'url', 'value' => ['url' => '/contacte'], 'label' => ['ca' => 'Contacte']]], 'o' => ['background' => 'primary']]);
        $this->assertTrue($response->isRedirect());
        $sections = (new PageSections())->all($page);
        $this->assertCount(1, $sections);
        $this->assertSame('primary', $sections[0]->options['background']);
        $this->assertSame(['type' => 'url', 'value' => '/contacte'], $sections[0]->values['link']);
        $this->assertSame('section_added', db_connect()->table('activity_log')->orderBy('id', 'desc')->get(1)->getRowArray()['action'] ?? null);
        $this->assertContains('sections', (new Revisions())->history($page)[0]['changes'], 'The change is kept in a revision.');

        // Errors: the form again (422) with the message, nothing written.
        $response = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['id' => (string) $sections[0]->id, 'f' => ['title' => ['ca' => ''], 'link' => ['type' => 'url', 'value' => ['url' => 'javascript:alert(1)'], 'label' => ['ca' => 'X']]]]);
        $this->assertSame(422, $response->response()->getStatusCode());
        $this->assertSame('Vine!', $this->found($page, (int) $sections[0]->id)->texts['ca']['title']);

        // The type of a section cannot be changed by a forged post.
        $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['id' => (string) $sections[0]->id, 'type' => 'pages.text', 'f' => ['title' => ['ca' => 'Nou']]]);
        $this->assertSame('pages.cta', $this->found($page, (int) $sections[0]->id)->type);
        $this->assertSame('Nou', $this->found($page, (int) $sections[0]->id)->texts['ca']['title']);

        $this->assertNotFound('GET', 'backend/pages/' . $page . '/sections/new/evil.type');
        $this->assertNotFound('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'evil.type', 'f' => []]);
    }

    public function testSectionsBelongToTheirPage(): void
    {
        $a       = $this->page('A', ['layout' => 'sections']);
        $b       = $this->page('B', ['layout' => 'sections']);
        $section = $this->textSection($a, 'De la pàgina A');
        $trashed = $this->page('Paperera', ['deleted_at' => date('Y-m-d H:i:s')]);
        $this->editor();

        $this->assertNotFound('GET', 'backend/pages/' . $b . '/sections/' . $section);
        $this->assertNotFound('POST', 'backend/pages/' . $b . '/sections/save', ['id' => (string) $section, 'f' => ['text' => ['ca' => '<p>Robat</p>']]]);
        $this->send('POST', 'backend/pages/' . $b . '/sections/' . $section . '/delete');
        $this->send('POST', 'backend/pages/' . $b . '/sections/' . $section . '/hide', ['hidden' => '1']);
        $this->send('POST', 'backend/pages/' . $b . '/sections/order', ['order' => json_encode([$section])]);

        $this->assertSame(['De la pàgina A'], $this->order($a));
        $this->assertFalse($this->found($a, $section)->hidden);
        $this->assertSame(0, (new PageSections())->count($b));
        $this->assertNotFound('GET', 'backend/pages/' . $trashed . '/sections');
    }

    public function testOrderHideDuplicateAndDelete(): void
    {
        $page  = $this->page('Portada', ['layout' => 'sections']);
        $one   = $this->textSection($page, 'U');
        $two   = $this->textSection($page, 'Dos');
        $three = $this->textSection($page, 'Tres');
        $this->editor();

        $this->send('POST', 'backend/pages/' . $page . '/sections/order', ['order' => json_encode([$three, $one, $two])]);
        $this->assertSame(['Tres', 'U', 'Dos'], $this->order($page));

        $this->send('POST', 'backend/pages/' . $page . '/sections/order', ['order' => json_encode([$three, $one])]);
        $this->send('POST', 'backend/pages/' . $page . '/sections/order', ['order' => json_encode([$three, $one, $two, 999])]);
        $this->send('POST', 'backend/pages/' . $page . '/sections/order', ['order' => '{"a":1}']);
        $this->assertSame(['Tres', 'U', 'Dos'], $this->order($page), 'An order that is not the page\'s sections is refused.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/order', ['move' => $two . ':up']);
        $this->assertSame(['Tres', 'Dos', 'U'], $this->order($page), 'The button without JavaScript.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/' . $three . '/duplicate');
        $this->assertSame(['Tres', 'Tres', 'Dos', 'U'], $this->order($page));

        $this->send('POST', 'backend/pages/' . $page . '/sections/' . $two . '/hide', ['hidden' => '1']);
        $this->assertTrue($this->found($page, $two)->hidden);
        $public = $this->body('GET', 'portada');
        $this->assertStringNotContainsString('<p>Dos</p>', $public, 'A hidden section is not shown.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/' . $one . '/delete');
        $this->assertSame(['Tres', 'Tres', 'Dos'], $this->order($page));
        $this->assertSame(0, db_connect()->table('page_section_translations')->where('section_id', $one)->countAllResults());
    }

    public function testRevisionsRestoreTheSections(): void
    {
        $page = $this->page('Portada', ['layout' => 'sections']);
        $this->textSection($page, 'Primera versió');
        $first = (new Revisions())->record($page, null, 'create');
        $this->assertNotNull($first);

        (new PageSections())->replace($page, []);
        $this->textSection($page, 'Segona versió');
        $this->textSection($page, 'Una altra');
        (new Revisions())->record($page, null);
        $this->assertSame(['Segona versió', 'Una altra'], $this->order($page));
        $this->assertArrayHasKey('sections', (new Revisions())->diff($page, (int) (new Revisions())->history($page)[0]['id']));

        (new Revisions())->restore($page, $first, null);
        $this->assertSame(['Primera versió'], $this->order($page));
    }

    public function testSwitchingTheLayoutKeepsTheOtherContent(): void
    {
        $page = $this->page('Qui som');
        $this->textSection($page, 'Una secció');
        $this->editor();

        $this->assertStringContainsString('<p>Text de Qui som</p>', $this->body('GET', 'qui-som'), 'Article: the body.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/layout', ['layout' => 'sections']);
        $this->assertSame('sections', $this->row($page)['layout']);
        $html = $this->body('GET', 'qui-som');
        $this->assertStringContainsString('<p>Una secció</p>', $html);
        $this->assertStringNotContainsString('Text de Qui som', $html);
        $this->assertStringContainsString('site-main-full', $html, 'Full-width layout.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/layout', ['layout' => 'article']);
        $this->assertStringContainsString('<p>Text de Qui som</p>', $this->body('GET', 'qui-som'));
        $this->assertSame(1, (new PageSections())->count($page), 'The sections are kept.');
        $this->assertSame('<p>Text de Qui som</p>', (string) $this->text($page)['body'], 'The body is kept.');

        $this->send('POST', 'backend/pages/' . $page . '/sections/layout', ['layout' => 'evil']);
        $this->assertSame('article', $this->row($page)['layout']);
    }

    public function testEachTypeRendersItsMarkup(): void
    {
        $page    = $this->page('Portada', ['layout' => 'sections']);
        $contact = $this->page('Contacte');
        $link    = ['type' => 'pages.page', 'value' => (string) $contact];

        $this->section($page, 'pages.hero', ['primary' => $link, 'primary_style' => 'primary', 'height' => 'l', 'edge' => 'wave', 'align' => 'center'], ['ca' => ['title' => 'Benvinguts', 'text' => 'Hola', 'primary' => 'Contacte']], ['background' => 'dark']);
        $this->section($page, 'pages.links', ['items' => [['icon' => 'bi-house', 'link' => $link], ['icon' => 'bi-star', 'link' => ['type' => 'pages.page', 'value' => '99999']]], 'columns' => '5'], ['ca' => ['heading' => 'Accés', 'items' => [['label' => 'Inici'], ['label' => 'Desapareguda']]]], ['style' => 'band', 'background' => 'primary']);
        $this->section($page, 'pages.cards', ['cards' => [['link' => null]], 'columns' => '3'], ['ca' => ['heading' => 'Projectes', 'cards' => [['title' => 'Lectura', 'text' => 'Llibres']]]]);
        $this->section($page, 'pages.media_text', ['image_side' => 'end'], ['ca' => ['title' => 'Entorn', 'text' => '<p>Barri</p>']]);
        $this->section($page, 'pages.cta', ['link' => $link, 'button_style' => 'light'], ['ca' => ['title' => 'Vine', 'link' => 'Escriu-nos']], ['background' => 'primary']);
        $this->textSection($page, 'Final');

        $html = $this->body('GET', 'portada');

        $this->assertStringContainsString('<h1 class="visually-hidden">Portada</h1>', $html);
        $this->assertSame(6, substr_count($html, '<section class="kp-section'));
        $this->assertStringContainsString('kp-hero kp-hero-l kp-hero-edge-wave', $html);
        $this->assertStringContainsString('class="kp-edge-wave"', $html);
        $this->assertMatchesRegularExpression('#<a class="btn btn-primary btn-lg[^"]*" href="' . preg_quote(site_url('contacte'), '#') . '">Contacte</a>#', $html);
        $this->assertStringContainsString('kp-links kp-links-band', $html);
        $this->assertStringContainsString('row-cols-lg-5', $html);
        $this->assertStringContainsString('<i class="bi bi-house" aria-hidden="true"></i>', $html);
        $this->assertMatchesRegularExpression('#<span class="kp-link-item">\s*<span class="kp-link-media kp-link-icon"><i class="bi bi-star"#', $html, 'A link whose target is gone is shown without a link.');
        $this->assertStringContainsString('<h3 class="card-title h5 text-break">Lectura</h3>', $html);
        $this->assertStringContainsString('order-md-2', $html);
        $this->assertStringContainsString('<div class="kp-rich-text text-break"><p>Barri</p></div>', $html);
        $this->assertStringContainsString('btn btn-light btn-lg mt-2', $html);
        $this->assertStringContainsString('<p>Final</p>', $html);
        $this->assertStringNotContainsString('style=', $html, 'No inline styles (CSP).');
    }

    public function testSectionPagesKeepTheAudienceRules(): void
    {
        $page = $this->page('Intranet', ['layout' => 'sections', 'visibility' => 'private']);
        $this->textSection($page, 'Només per a usuaris');

        $this->assertTrue($this->send('GET', 'intranet')->isRedirect(), 'A guest goes to the login.');

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->assertStringContainsString('<p>Només per a usuaris</p>', $this->body('GET', 'intranet'));

        $draft = $this->page('Esborrany', ['layout' => 'sections', 'status' => 'draft']);
        $this->textSection($draft, 'Encara no');
        $this->assertNotFound('GET', 'esborrany');
    }

    public function testTranslationsFallBackOnThePublicPage(): void
    {
        $page = $this->page('Portada', ['layout' => 'sections'], ['es' => ['title' => 'Portada', 'slug' => 'portada']]);
        $this->section($page, 'pages.cta', ['button_style' => 'light'], ['ca' => ['title' => 'En català'], 'es' => ['title' => 'En castellano']]);
        $this->section($page, 'pages.text', ['column' => 'wide'], ['ca' => ['heading' => 'Només català', 'text' => '<p>Text</p>']]);

        $html = $this->body('GET', 'es/portada');
        $this->assertStringContainsString('En castellano', $html);
        $this->assertStringContainsString('Només català', $html, 'Missing Spanish text: the default language.');
    }

    public function testDuplicatingAPageCopiesItsSections(): void
    {
        $page = $this->page('Portada', ['layout' => 'sections']);
        $this->textSection($page, 'Copiada');
        $copy = (new \Modules\Pages\Services\PageCopier())->duplicate($page, null);

        $this->assertSame('sections', $this->row($copy)['layout']);
        $this->assertSame(['Copiada'], $this->order($copy));
    }
}
