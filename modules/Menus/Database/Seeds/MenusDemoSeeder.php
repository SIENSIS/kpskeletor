<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Menus\Services\LinkTypes;

/**
 * Demo data of the Menus module (`php spark modules:demo Menus`, after Pages and News: `AFTER`):
 *
 * - "Menú principal" in the `primary` location: Inici, Qui som (with Equip and Història), Notícies
 *   (with a news category), Contacte and Intranet (signed-in users only);
 * - "Peu de pàgina" in the `footer` location: the legal notice, contact and an external link
 *   opening in a new tab;
 * - the back-office menu: a custom group "Contingut" gathering News, Pages and Menus, a custom
 *   link to the public site, and for the `editor` group the group "Contingut" first.
 *
 * Page items use the Pages link type when the Pages demo exists, else the same paths as
 * addresses. Every row goes through `$demo`.
 */
final class MenusDemoSeeder extends DemoSeeder
{
    public const AFTER = ['Pages', 'News'];

    public function load(DemoData $demo): void
    {
        $now = Time::now()->toDateTimeString();

        $main = (int) $demo->insert('menus', ['name' => 'Menú principal', 'location' => 'primary', 'created_at' => $now, 'updated_at' => $now]);
        $this->item($demo, $main, null, 10, $this->page('inici', '/'), ['ca' => 'Inici', 'es' => 'Inicio', 'en' => 'Home'], ['icon' => 'bi bi-house']);
        $about = $this->item($demo, $main, null, 20, $this->page('qui-som'), ['ca' => 'Qui som', 'es' => 'Quiénes somos', 'en' => 'About us']);
        $this->item($demo, $main, $about, 10, $this->page('equip', '/qui-som/equip'), ['ca' => 'Equip', 'es' => 'Equipo', 'en' => 'Team']);
        $this->item($demo, $main, $about, 20, $this->page('historia', '/qui-som/historia'), ['ca' => 'Història', 'es' => 'Historia', 'en' => 'History']);
        $news = $this->item($demo, $main, null, 30, ['route', 'news-public'], ['ca' => 'Notícies', 'es' => 'Noticias', 'en' => 'News']);
        $category = $this->category();

        if ($category !== null) {
            $this->item($demo, $main, $news, 10, ['news.category', $category['slug']], ['ca' => $category['name']]);
        }

        $this->item($demo, $main, null, 40, $this->page('contacte'), ['ca' => 'Contacte', 'es' => 'Contacto', 'en' => 'Contact']);
        $this->item($demo, $main, null, 50, $this->page('intranet'), ['ca' => 'Intranet'], ['visibility' => 'users', 'icon' => 'bi bi-lock']);

        $footer = (int) $demo->insert('menus', ['name' => 'Peu de pàgina', 'location' => 'footer', 'created_at' => $now, 'updated_at' => $now]);
        $this->item($demo, $footer, null, 10, $this->page('avis-legal'), ['ca' => 'Avís legal', 'es' => 'Aviso legal', 'en' => 'Legal notice']);
        $this->item($demo, $footer, null, 20, $this->page('contacte'), ['ca' => 'Contacte', 'es' => 'Contacto', 'en' => 'Contact']);
        $this->item($demo, $footer, null, 30, ['url', 'https://www.example.com/'], ['ca' => 'Xarxa d’entitats', 'es' => 'Red de entidades', 'en' => 'Partner network'], ['target' => '_blank', 'icon' => 'bi bi-box-arrow-up-right']);

        // Back-office menu: a custom group and link, and a few relocations.
        $content = (int) $demo->insert('backend_menu_links', [
            'kind' => 'group', 'type' => '', 'value' => '', 'icon' => 'bi bi-collection', 'target' => '_self', 'permission' => '',
            'labels' => json_encode(['ca' => 'Contingut', 'es' => 'Contenido', 'en' => 'Content'], JSON_UNESCAPED_UNICODE),
            'groups' => '[]', 'parent' => null, 'zone' => 'sidebar', 'position' => 15, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $demo->insert('backend_menu_links', [
            'kind' => 'link', 'type' => 'url', 'value' => '/', 'icon' => 'bi bi-globe2', 'target' => '_blank', 'permission' => '',
            'labels' => json_encode(['ca' => 'Web pública', 'es' => 'Web pública', 'en' => 'Public website'], JSON_UNESCAPED_UNICODE),
            'groups' => '[]', 'parent' => null, 'zone' => 'sidebar', 'position' => 95, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $group = 'custom.' . $content;

        foreach (['News.read' => 10, 'Pages.pages' => 20, 'Menus.public' => 30] as $key => $position) {
            $demo->insert('backend_menu_customisations', ['entry_key' => $key, 'group_name' => null, 'parent' => $group, 'position' => $position, 'updated_at' => $now]);
        }

        $demo->insert('backend_menu_customisations', ['entry_key' => $group, 'group_name' => 'editor', 'position' => 2, 'updated_at' => $now]);

        (new CacheInvalidator())->invalidate(['menus']);
    }

    /**
     * @param array{0: string, 1: string}   $target type, value
     * @param array<string, string>          $labels
     * @param array<string, string>          $extra
     */
    private function item(DemoData $demo, int $menu, ?int $parent, int $position, array $target, array $labels, array $extra = []): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('menu_items', $extra + [
            'menu_id' => $menu, 'parent_id' => $parent, 'position' => $position, 'type' => $target[0], 'value' => $target[1],
            'icon' => '', 'target' => '_self', 'css_class' => '', 'visibility' => 'all', 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ($labels as $locale => $label) {
            $demo->insert('menu_item_translations', ['item_id' => $id, 'locale' => $locale, 'label' => $label]);
        }

        return $id;
    }

    /**
     * A page of the Pages demo by its Catalan slug (`pages.page`), else its address.
     *
     * @return array{0: string, 1: string}
     */
    private function page(string $slug, ?string $path = null): array
    {
        $db = db_connect();

        if ($db->tableExists('page_translations')) {
            $row = $db->table('page_translations')->select('page_id')->where('locale', 'ca')->where('slug', $slug)->get()->getRowArray();

            if (is_array($row)) {
                return ['pages.page', (string) $row['page_id']];
            }
        }

        return ['url', $path ?? '/' . $slug];
    }

    /**
     * The first choice of the news category link type, if News is enabled and has categories.
     *
     * @return array{slug: string, name: string}|null
     */
    private function category(): ?array
    {
        $options = (new LinkTypes())->get('news.category')?->options() ?? [];

        foreach ($options as $slug => $name) {
            return ['slug' => (string) $slug, 'name' => $name];
        }

        return null;
    }
}
