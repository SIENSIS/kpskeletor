<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use Modules\Core\Menus\PublicMenuItem;
use Modules\Core\Tests\ContentLocalesTrait;
use Modules\Menus\Services\MenuRenderer;

/**
 * Menu labels follow the site's languages (session 37, docs/i18n.md "Content languages"): with a
 * fourth language and Spanish as the default, the item and custom link forms show one label per
 * language with Spanish required, saving needs Spanish only, menus fall back to Spanish and labels
 * of a language no longer offered are kept.
 *
 * @internal
 */
final class ContentLocalesTest extends MenusTestCase
{
    use ContentLocalesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useContentLocales();
        $this->loginAs('admin');
    }

    public function testTheItemAndCustomLinkFormsOfferEveryLanguageWithTheDefaultRequired(): void
    {
        $menu = $this->menu();

        foreach (['backend/menus/items/' . $menu . '/new', 'backend/menus/backend/link/new'] as $path) {
            $html = (string) $this->send('GET', $path)->response()->getBody();

            foreach (['ca', 'es', 'en', 'fr'] as $locale) {
                $this->assertMatchesRegularExpression('/<input[^>]*name="labels\[' . $locale . '\]"[^>]*>/', $html, "{$path}: {$locale}.");
                preg_match('/<input[^>]*name="labels\[' . $locale . '\]"[^>]*>/', $html, $tag);
                $this->assertSame($locale === 'es', str_contains($tag[0], ' required'), "{$path}: only es is required.");
            }

            $this->assertMatchesRegularExpression('/for="f-label-es">[^<]*\*<\/label>/', $html, 'The marker on the default language.');
            $this->assertDoesNotMatchRegularExpression('/for="f-label-ca">[^<]*\*<\/label>/', $html);
        }
    }

    public function testAnItemNeedsTheDefaultLanguageOnly(): void
    {
        $menu = $this->menu();
        $form = ['id' => '0', 'type' => 'url', 'value' => 'https://example.com/', 'parent_id' => '', 'icon' => '', 'target' => '_self', 'css_class' => '', 'visibility' => 'all'];

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $form + ['labels' => ['ca' => 'Inici', 'es' => '', 'en' => '', 'fr' => '']]);
        $this->assertSame(0, db_connect()->table('menu_items')->countAllResults(), 'Without Spanish: refused.');

        $this->send('POST', 'backend/menus/items/' . $menu . '/save', $form + ['labels' => ['ca' => '', 'es' => 'Inicio', 'en' => '', 'fr' => 'Accueil']])->assertRedirect();
        $this->assertSame(['es' => 'Inicio', 'fr' => 'Accueil'], array_column(db_connect()->table('menu_item_translations')->orderBy('locale')->get()->getResultArray(), 'label', 'locale'));
        $this->seeInDatabase('activity_log', ['module' => 'Menus', 'action' => 'item_added', 'summary' => 'Principal › Inicio']);
    }

    public function testACustomLinkNeedsTheDefaultLanguageOnly(): void
    {
        $form = ['kind' => 'link', 'type' => 'url', 'value' => 'https://example.com/help', 'target' => '_self', 'zone' => 'sidebar', 'position' => '10'];

        $this->send('POST', 'backend/menus/backend/custom/save', $form + ['labels' => ['ca' => 'Ajuda']]);
        $this->dontSeeInDatabase('backend_menu_links', ['value' => 'https://example.com/help']);

        $this->send('POST', 'backend/menus/backend/custom/save', $form + ['labels' => ['es' => 'Ayuda']]);
        $this->seeInDatabase('backend_menu_links', ['value' => 'https://example.com/help', 'labels' => '{"es":"Ayuda"}']);
    }

    public function testMenusFallBackToTheDefaultLanguageAndKeepLabelsOfLanguagesNoLongerOffered(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, 'Inici');
        db_connect()->table('menu_item_translations')->insert(['item_id' => $item, 'locale' => 'es', 'label' => 'Inicio']);

        $labels = static fn (array $items): array => array_map(static fn (PublicMenuItem $i): string => $i->label, $items);
        $this->assertSame(['Inicio'], $labels((new MenuRenderer())->items('primary', null, 'fr')), 'No French label: the default language, not Catalan.');

        setting('Core.locales', ['es', 'en', 'fr']);
        $this->send('POST', 'backend/menus/items/' . $menu . '/save', [
            'id' => (string) $item, 'labels' => ['es' => 'Portada', 'en' => '', 'fr' => ''], 'type' => 'url', 'value' => '/',
            'parent_id' => '', 'icon' => '', 'target' => '_self', 'css_class' => '', 'visibility' => 'all',
        ])->assertRedirect();

        $this->assertSame(['ca' => 'Inici', 'es' => 'Portada'], array_column(db_connect()->table('menu_item_translations')->where('item_id', $item)->orderBy('locale')->get()->getResultArray(), 'label', 'locale'), 'Catalan is kept; English, emptied, is removed.');
    }
}
