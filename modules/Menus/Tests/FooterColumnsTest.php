<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

/**
 * The default theme's footer in columns (session 47; docs/menus.md, "Footer columns"): top-level
 * items of the `footer` menu with children become columns (heading + links), items without
 * children go in a row below; the theme options `footerColumns` and `footerContact` (translated,
 * lines separated by `|`); a footer menu without children keeps its single line.
 *
 * @internal
 */
final class FooterColumnsTest extends MenusTestCase
{
    protected function tearDown(): void
    {
        service('settings')->forget('Theme.footerColumns', 'theme:default');
        service('settings')->forget('Theme.footerContact', 'theme:default');
        parent::tearDown();
    }

    private function footer(): string
    {
        $html = (string) $this->send('GET', 'en')->response()->getBody();
        $start = strpos($html, '<footer');
        $this->assertNotFalse($start);

        return substr($html, $start, (int) strpos($html, '</footer>') - $start);
    }

    public function testWithoutChildrenTheFooterKeepsItsLine(): void
    {
        $footer = $this->menu('Peu', 'footer');
        $this->item($footer, 'Avís legal', 'url', '/avis-legal');

        $html = $this->footer();
        $this->assertStringContainsString('data-menu="footer"', $html);
        $this->assertStringContainsString('Avís legal (en)', $html);
        $this->assertStringNotContainsString('site-footer-columns', $html);
    }

    public function testItemsWithChildrenBecomeColumns(): void
    {
        $footer  = $this->menu('Peu', 'footer');
        $centre  = $this->item($footer, 'El centre', 'url', '/qui-som', ['position' => 1]);
        $this->item($footer, 'Història', 'url', '/historia', ['parent_id' => $centre, 'position' => 1]);
        $this->item($footer, 'Equip', 'url', '/equip', ['parent_id' => $centre, 'position' => 2]);
        $services = $this->item($footer, 'Serveis', 'url', '/serveis', ['position' => 2]);
        $this->item($footer, 'Biblioteca', 'url', 'https://example.com/b', ['parent_id' => $services, 'target' => '_blank']);
        $this->item($footer, 'Avís legal', 'url', '/avis-legal', ['position' => 3]);

        $html = $this->footer();
        $this->assertStringContainsString('site-footer-columns', $html);
        $this->assertStringContainsString('row-cols-lg-4', $html, 'Four columns by default.');
        $this->assertStringContainsString('aria-label="' . esc('El centre (en)', 'attr') . '"', $html, 'Each column is a labelled navigation.');
        $this->assertMatchesRegularExpression('/<h2 class="h6 site-footer-heading">\s*<a[^>]*>El centre \(en\)<\/a>/', $html);
        $this->assertLessThan(strpos($html, 'Equip (en)'), strpos($html, 'Història (en)'), 'Children in order.');
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        $this->assertGreaterThan(strpos($html, 'Biblioteca (en)'), strpos($html, 'Avís legal (en)'), 'Items without children go below the columns.');
    }

    public function testTheColumnsAndTheContactColumnAreThemeOptions(): void
    {
        $footer = $this->menu('Peu', 'footer');
        $centre = $this->item($footer, 'El centre', 'url', '/qui-som');
        $this->item($footer, 'Equip', 'url', '/equip', ['parent_id' => $centre]);
        service('settings')->set('Theme.footerColumns', '3', 'theme:default');
        service('settings')->set('Theme.footerContact', ['ca' => 'Carrer Major, 1 | 08000 Ciutat', 'en' => 'Main Street, 1 | <b>City</b>'], 'theme:default');

        $html = $this->footer();
        $this->assertStringContainsString('row-cols-lg-3', $html);
        $this->assertMatchesRegularExpression('/<address[^>]*>\s*Main Street, 1<br>&lt;b&gt;City&lt;\/b&gt;/', $html, 'The visitor\'s language, one line per |, escaped.');

        // An invalid stored number of columns falls back to the default.
        service('settings')->set('Theme.footerColumns', '99" onclick="x', 'theme:default');
        $this->assertStringContainsString('row-cols-lg-4', $this->footer());
    }

    public function testTheContactColumnAloneShowsTheColumns(): void
    {
        service('settings')->set('Theme.footerContact', ['ca' => 'Carrer Major, 1'], 'theme:default');

        $html = $this->footer();
        $this->assertStringContainsString('site-footer-contact', $html);
        $this->assertStringContainsString('Carrer Major, 1', $html, 'Falls back to the default language.');
    }
}
