<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\Core\Security\HtmlSanitizer;
use Modules\Pages\Services\Revisions;

/**
 * Demo data of the Pages module (`php spark modules:demo Pages`): a home page ("Inici", in
 * Catalan, Spanish and English), "Qui som" with two subpages (template "sidebar"), "Contacte", a
 * legal notice hidden from search engines, an intranet page for signed-in users with a subpage
 * for the `editor` group, a draft and a scheduled page. Each page gets its first revision. Every
 * row goes through `$demo`. To show "Inici" at the site's address, set Settings › Pages ›
 * Home page to `inici`.
 */
final class PagesDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $now    = Time::now();
        $author = $this->userId('admin@example.com') ?? $this->userId('editor@example.com');

        $this->page($demo, $now, $author, [
            'template' => 'wide', 'position' => 1,
            'texts'    => [
                'ca' => ['Inici', 'inici', 'Benvinguts al portal de l’organització.', '<h2 class="text-center">Benvinguts</h2><p class="text-center">Aquest és el portal públic de l’organització: hi trobareu qui som, les notícies i com contactar-nos.</p><ul><li>Notícies i avisos</li><li>Calendari d’activitats</li><li>Tràmits en línia</li></ul>'],
                'es' => ['Inicio', 'inicio', 'Bienvenidos al portal de la organización.', '<h2 class="text-center">Bienvenidos</h2><p class="text-center">Este es el portal público de la organización.</p>'],
                'en' => ['Home', 'home', 'Welcome to the organisation’s website.', '<h2 class="text-center">Welcome</h2><p class="text-center">This is the public website of the organisation.</p>'],
            ],
        ]);

        $about = $this->page($demo, $now, $author, [
            'template' => 'sidebar', 'position' => 2,
            'texts'    => [
                'ca' => ['Qui som', 'qui-som', 'Una organització oberta al servei de les persones.', '<p>Treballem des de fa més de vint anys per oferir serveis <strong>propers, accessibles i segurs</strong>.</p><blockquote>La tecnologia ha d’estar al servei de les persones.</blockquote>'],
                'es' => ['Quiénes somos', 'quienes-somos', 'Una organización abierta al servicio de las personas.', '<p>Trabajamos desde hace más de veinte años para ofrecer servicios cercanos, accesibles y seguros.</p>'],
            ],
            'meta' => ['ca' => ['Qui som · L’organització', 'Coneixeu l’equip i la història de l’organització.']],
        ]);

        $this->page($demo, $now, $author, [
            'parent' => $about, 'position' => 1,
            'texts'  => ['ca' => ['Equip', 'equip', 'Les persones que fan possible el projecte.', '<p>L’equip està format per persones de perfils molt diversos.</p><ol><li>Direcció</li><li>Coordinació</li><li>Suport tècnic</li></ol>']],
        ]);

        $this->page($demo, $now, $author, [
            'parent' => $about, 'position' => 2,
            'texts'  => ['ca' => ['Història', 'historia', 'D’on venim.', '<p>Vam començar l’any 2004 amb un petit grup de voluntaris.</p>']],
        ]);

        $this->page($demo, $now, $author, [
            'position' => 3,
            'texts'    => ['ca' => ['Contacte', 'contacte', 'On som i com contactar-nos.', '<p>Escriviu-nos a <a href="mailto:info@example.com">info@example.com</a> o veniu a veure’ns de dilluns a divendres de 9 a 14 h.</p>']],
        ]);

        $this->page($demo, $now, $author, [
            'position' => 9, 'noindex' => 1,
            'texts'    => ['ca' => ['Avís legal', 'avis-legal', '', '<p class="text-justify">Aquest lloc web és titularitat de l’organització. L’ús del lloc implica l’acceptació d’aquestes condicions.</p>']],
        ]);

        $intranet = $this->page($demo, $now, $author, [
            'position' => 5, 'visibility' => 'private', 'template' => 'sidebar',
            'texts'    => ['ca' => ['Intranet', 'intranet', 'Informació per a les persones usuàries identificades.', '<p>Documents i procediments interns.</p>']],
        ]);

        $this->page($demo, $now, $author, [
            'parent' => $intranet, 'position' => 1, 'visibility' => 'groups', 'groups' => ['editor'],
            'texts'  => ['ca' => ['Guia d’estil per a editors', 'guia-estil', 'Com escriure i publicar continguts.', '<h3>Títols</h3><p>Curts i clars.</p><h3>Imatges</h3><p>Sempre amb text alternatiu.</p>']],
        ]);

        $this->page($demo, $now, $author, [
            'position' => 7, 'status' => 'draft', 'publish_at' => null,
            'texts'    => ['ca' => ['Pàgina en preparació', 'en-preparacio', 'Encara no és pública.', '<p>Text pendent de revisió.</p>']],
        ]);

        $this->page($demo, $now, $author, [
            'position' => 8, 'status' => 'scheduled', 'publish_at' => $now->addDays(10),
            'texts'    => ['ca' => ['Jornades de portes obertes', 'jornades', 'Programa de les jornades.', '<p>Es publicarà deu dies abans de les jornades.</p>']],
        ]);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function page(DemoData $demo, Time $now, ?int $author, array $page): int
    {
        $publishAt = array_key_exists('publish_at', $page) ? $page['publish_at'] : $now->subDays(7);

        $id = (int) $demo->insert('pages', [
            'parent_id'  => $page['parent'] ?? null,
            'position'   => $page['position'] ?? 0,
            'template'   => $page['template'] ?? 'default',
            'status'     => $page['status'] ?? 'published',
            'publish_at' => $publishAt instanceof Time ? $publishAt->toDateTimeString() : null,
            'visibility' => $page['visibility'] ?? 'public',
            'noindex'    => $page['noindex'] ?? 0,
            'author_id'  => $author,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        foreach ($page['texts'] as $locale => [$title, $slug, $summary, $body]) {
            $meta = $page['meta'][$locale] ?? ['', ''];
            $demo->insert('page_translations', [
                'page_id'          => $id,
                'locale'           => $locale,
                'title'            => $title,
                'slug'             => $slug,
                'summary'          => $summary,
                'body'             => HtmlSanitizer::clean($body),
                'meta_title'       => $meta[0],
                'meta_description' => $meta[1],
                'canonical'        => '',
            ]);
        }

        foreach ($page['groups'] ?? [] as $group) {
            $row = db_connect()->table('groups')->select('id')->where('name', $group)->get()->getRowArray();

            if (is_array($row)) {
                $demo->insert('page_groups', ['page_id' => $id, 'group_id' => (int) $row['id']]);
            }
        }

        $revision = (new Revisions())->record($id, $author, 'create');

        if ($revision !== null) {
            $demo->record('page_revisions', $revision);
        }

        return $id;
    }

    private function userId(string $email): ?int
    {
        $row = db_connect()->table('auth_identities')->select('user_id')->where('type', 'email_password')->where('secret', $email)->get()->getRowArray();

        return is_array($row) ? (int) $row['user_id'] : null;
    }
}
