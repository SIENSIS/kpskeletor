<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Standard views and small components: escaping, allowed values, links.
 *
 * @internal
 */
final class UiComponentsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('theme')->reset();
    }

    private function clean(string $html): string
    {
        return trim((string) preg_replace('/<!-- DEBUG-VIEW [^>]*-->\s*/', '', $html));
    }

    public function testComponentsSeeOnlyTheirOwnData(): void
    {
        // A view rendered with the shared renderer saves its data (CodeIgniter's default)…
        view('Modules\\Core\\Views\\components\\badge', ['text' => 'Before', 'variant' => 'info', 'message' => 'Leaked text', 'action' => ['label' => 'Leaked action', 'url' => '/x']]);

        // …but a component given no message nor action must not show them.
        $html = theme_component('empty_state', ['title' => 'Nothing here']);

        $this->assertStringContainsString('Nothing here', $html);
        $this->assertStringNotContainsString('Leaked text', $html);
        $this->assertStringNotContainsString('Leaked action', $html);
    }

    public function testBadgeEscapesAndWhitelistsTheVariant(): void
    {
        $html = $this->clean(ui_badge('<b>x</b>', 'primary" onclick="alert(1)'));

        $this->assertStringContainsString('text-bg-secondary', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('bi bi-check', ui_badge('Ok', 'success', 'bi-check'));
        $this->assertStringNotContainsString('evil', ui_badge('Ok', 'success', 'bi-x" data-evil="1'));
    }

    public function testStatusColours(): void
    {
        $this->assertStringContainsString('bg-success-subtle', ui_status('published'));
        $this->assertStringContainsString('bg-warning-subtle', ui_status('pending'));
        $this->assertStringContainsString('bg-danger-subtle', ui_status('failed'));
        $this->assertStringContainsString('bg-secondary-subtle', ui_status('whatever', 'Whatever'));
        $this->assertStringContainsString('data-status="draft"', ui_status('draft', 'Esborrany'));
    }

    public function testAvatarInitialsAndImages(): void
    {
        $this->assertStringContainsString('>AP<', $this->clean(ui_avatar('Anna Puig')));
        $this->assertStringContainsString('avatar-lg', ui_avatar('Anna', null, 'lg'));
        $this->assertStringContainsString('avatar-md', ui_avatar('Anna', null, 'huge'));
        $this->assertStringContainsString('<img src="#"', ui_avatar('Anna', 'javascript:alert(1)'));
        $this->assertMatchesRegularExpression('/avatar-c[1-8]/', ui_avatar('Anna'));
    }

    public function testDatesAndPaginationInfo(): void
    {
        $this->assertStringContainsString('datetime="2026-11-02T10:30:00', ui_date('2026-11-02 10:30:00'));
        $this->assertStringContainsString('—', ui_date(null));
        $this->assertStringContainsString('11–20', ui_pagination_info(2, 10, 45));
        $this->assertStringContainsString('41–45', ui_pagination_info(5, 10, 45));
        $this->assertStringContainsString(lang('Core.ui.paginationEmpty'), ui_pagination_info(1, 10, 0));
    }

    public function testRelativeDatesAreTranslatedToCatalan(): void
    {
        $locale = service('request')->getLocale();
        service('request')->setLocale('ca');
        service('language')->setLocale('ca');

        try {
            $this->assertStringContainsString('fa 5 hores', ui_date(date('Y-m-d H:i:s', time() - 5 * 3600 - 60), 'relative'));
            $this->assertStringContainsString('fa 2 dies', ui_date(date('Y-m-d H:i:s', time() - 2 * 86400 - 60), 'relative'));
        } finally {
            service('request')->setLocale($locale);
            service('language')->setLocale($locale);
        }
    }

    public function testLinksAreSafe(): void
    {
        $this->assertSame('#', ui_safe_url('javascript:alert(1)'));
        $this->assertSame('#', ui_safe_url('data:text/html,x'));
        $this->assertSame('#', ui_safe_url('//evil.example.com'));
        $this->assertSame('/backend/news', ui_safe_url('/backend/news'));
        $this->assertSame('https://example.com/a', ui_safe_url('https://example.com/a'));
        $this->assertSame('news/1', ui_safe_url('news/1'));

        $external = ui_link('Docs', 'https://getbootstrap.com/');
        $this->assertStringContainsString('rel="noopener noreferrer"', $external);
        $this->assertStringNotContainsString('noopener', ui_link('Home', base_url('/')));
        $this->assertStringContainsString('bi-file-earmark-pdf', ui_file_link('a.pdf', '/f/1', 2048));
        $this->assertStringContainsString('2.0 KB', ui_file_link('a.pdf', '/f/1', 2048));
        $this->assertStringContainsString('<a href="/u/1">Anna</a>', $this->clean(ui_user_link('Anna', '/u/1')));
    }

    public function testStandardViews(): void
    {
        $header = $this->clean(theme_component('page_header', [
            'title'       => 'News <x>',
            'breadcrumbs' => [['label' => 'Back office', 'url' => '/backend'], ['label' => 'News', 'url' => null]],
            'actions'     => [['label' => 'New', 'url' => 'javascript:alert(1)', 'icon' => 'bi-plus']],
        ]));
        $this->assertStringContainsString('News &lt;x&gt;', $header);
        $this->assertStringContainsString('aria-current="page"', $header);
        $this->assertStringContainsString('href="#"', $header);

        $form = theme_component('form_layout', ['action' => '/backend/news/save', 'fields' => '<input name="t">', 'hidden' => ['id' => '"1"']]);
        $this->assertStringContainsString('method="post"', $form);
        $this->assertStringContainsString('name="' . csrf_token() . '"', $form);
        $this->assertStringContainsString('value="&quot;1&quot;"', $form);

        $tabs = theme_component('tabs', ['id' => 'x"y', 'tabs' => [['id' => 'a', 'label' => 'A', 'content' => 'A!'], ['id' => 'b', 'label' => 'B', 'content' => 'B!', 'active' => true]]]);
        $this->assertStringContainsString('id="xy-b" role="tabpanel"', str_replace('tab-pane fade show active" ', '', $tabs));
        $this->assertStringContainsString('aria-selected="true"', $tabs);

        $this->assertStringContainsString('empty-state', theme_component('empty_state', ['title' => 'Empty']));
        $this->assertStringContainsString('crud-container', theme_component('crud_container', ['content' => '<table></table>']));
        $this->assertStringContainsString('card-footer', theme_component('card', ['title' => 'T', 'body' => 'b', 'footer' => 'f']));
    }
}
