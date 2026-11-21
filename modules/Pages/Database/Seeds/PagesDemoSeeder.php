<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\Core\Sections\SectionData;
use Modules\Core\Security\HtmlSanitizer;
use Modules\Core\Storage\Storage;
use Modules\Pages\Services\PageSections;
use Modules\Pages\Services\Revisions;

/**
 * Demo data of the Pages module (`php spark modules:demo Pages`): a home page ("Inici", in
 * Catalan, Spanish and English), "Qui som" with two subpages (template "sidebar"), "Contacte", a
 * legal notice hidden from search engines, an intranet page for signed-in users with a subpage
 * for the `editor` group, a draft and a scheduled page, and "Portada amb seccions", a published
 * page built from the six section types of the module (D-45-01) with placeholder illustrations
 * drawn here (no third-party images). Each page gets its first revision. Every row goes through
 * `$demo`. To show "Inici" at the site's address, choose it in Settings › Pages ›
 * Home page.
 */
final class PagesDemoSeeder extends DemoSeeder
{
    /**
     * The slug of the demo page "Intranet": `intranet`, or `area-interna` when the back office
     * itself lives at `/intranet` (D-40-01).
     */
    public static function intranetSlug(): string
    {
        return backend_path() === 'intranet' ? 'area-interna' : 'intranet';
    }

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
            'texts'    => ['ca' => ['Intranet', self::intranetSlug(), 'Informació per a les persones usuàries identificades.', '<p>Documents i procediments interns.</p>']],
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

        $this->sectionsPage($demo, $now, $author, $about);
        $this->moduleSectionPages($demo, $now, $author);
    }

    /**
     * Session 46: "Notícies" and "Sortides" (a banner and the news of their categories), "Agenda"
     * (a banner, the upcoming events and the calendar) and "Més seccions" (carousel, testimonials,
     * logos, a gallery and an embed that needs its host allowed in Settings › Security). News,
     * Events and Files categories and folders are looked up by their demo slugs; without them
     * the lists show every category.
     */
    private function moduleSectionPages(DemoData $demo, Time $now, ?int $author): void
    {
        $image   = fn (string $name, array $from, array $to, int $width = 1600, int $height = 520): string => $this->illustration($demo, $name, $width, $height, $from, $to);
        $tr      = static fn (array $ca, array $es, array $en): array => ['ca' => $ca, 'es' => $es, 'en' => $en];
        $opts    = static fn (array $options): array => $options + ['style' => '', 'background' => 'none', 'spacing' => 'm', 'width' => 'contained', 'background_image' => '', 'anchor' => ''];
        $hero    = static fn (string $key): array => ['primary' => null, 'primary_style' => 'primary', 'secondary' => null, 'secondary_style' => 'outline-light', 'background' => ['key' => $key, 'decorative' => true], 'overlay' => 'dark', 'side_image' => null, 'height' => 's', 'align' => 'start', 'edge' => 'wave'];
        $heroTr  = static fn (string $title, string $text): array => ['title' => $title, 'text' => $text, 'primary' => '', 'secondary' => '', 'background' => '', 'side_image' => ''];
        $newsCat = static function (string $slug): array {
            if (! db_connect()->tableExists('news_categories')) {
                return [];
            }

            $row = db_connect()->table('news_categories')->select('id')->where('slug', $slug)->get()->getRowArray();

            return is_array($row) ? [(string) $row['id']] : [];
        };
        $news    = static fn (string $mode, int $limit, array $categories, bool $search, string $columns = '3'): array => ['mode' => $mode, 'limit' => $limit, 'categories' => $categories, 'columns' => $columns, 'show_date' => true, 'show_summary' => true, 'show_image' => true, 'search' => $search, 'more' => null];

        $this->sectionPage($demo, $now, $author, 10, [
            'ca' => ['Notícies', 'noticies', 'Totes les notícies del centre.'],
            'es' => ['Noticias', 'noticias', 'Todas las noticias del centro.'],
            'en' => ['News', 'news-list', 'All the news of the school.'],
        ], [
            new SectionData('pages.hero', $hero($image('noticies-banner.png', [28, 60, 120], [40, 120, 170])), $tr($heroTr('Notícies', 'El que passa al centre, explicat per nosaltres.'), $heroTr('Noticias', 'Lo que pasa en el centro.'), $heroTr('News', 'What happens at school, told by us.')), $opts(['background' => 'dark', 'spacing' => 's', 'width' => 'full'])),
            new SectionData('news.list', $news('full', 6, [...$newsCat('institucional'), ...$newsCat('activitats')], true), $tr(['heading' => 'Comunicats', 'intro' => ''], ['heading' => 'Comunicados', 'intro' => ''], ['heading' => 'Announcements', 'intro' => '']), $opts(['style' => 'cards'])),
        ]);

        $this->sectionPage($demo, $now, $author, 11, [
            'ca' => ['Sortides', 'sortides', 'Excursions, colònies i visites del curs.'],
            'es' => ['Salidas', 'salidas', 'Excursiones, colonias y visitas del curso.'],
            'en' => ['Outings', 'outings', 'Trips, camps and visits of the year.'],
        ], [
            new SectionData('pages.hero', $hero($image('sortides-banner.png', [30, 110, 80], [120, 180, 90])), $tr($heroTr('Sortides', 'Aprenem fora de l’aula durant tot el curs.'), $heroTr('Salidas', 'Aprendemos fuera del aula.'), $heroTr('Outings', 'Learning outside the classroom all year round.')), $opts(['background' => 'dark', 'spacing' => 's', 'width' => 'full'])),
            new SectionData('news.list', $news('full', 4, $newsCat('sortides'), false, '4'), $tr(['heading' => '', 'intro' => ''], ['heading' => '', 'intro' => ''], ['heading' => '', 'intro' => '']), $opts(['style' => 'cards'])),
        ]);

        $this->sectionPage($demo, $now, $author, 12, [
            'ca' => ['Agenda', 'agenda', 'Les activitats públiques del centre.'],
            'es' => ['Agenda', 'agenda', 'Las actividades públicas del centro.'],
            'en' => ['Diary', 'diary', 'The public activities of the school.'],
        ], [
            new SectionData('pages.hero', $hero($image('agenda-banner.png', [90, 60, 140], [200, 110, 90])), $tr($heroTr('Agenda', 'Jornades, festes i cursos oberts a tothom.'), $heroTr('Agenda', 'Jornadas, fiestas y cursos abiertos a todo el mundo.'), $heroTr('Diary', 'Open days, festivals and courses open to everyone.')), $opts(['background' => 'dark', 'spacing' => 's', 'width' => 'full'])),
            new SectionData('events.list', ['categories' => [], 'limit' => 4, 'days' => 90, 'columns' => '4', 'more' => null], $tr(['heading' => 'Properes activitats', 'intro' => '', 'more' => ''], ['heading' => 'Próximas actividades', 'intro' => '', 'more' => ''], ['heading' => 'Upcoming events', 'intro' => '', 'more' => '']), $opts(['style' => 'cards', 'background' => 'light'])),
            new SectionData('events.calendar', ['categories' => [], 'view' => 'dayGridMonth', 'filter' => true], $tr(['heading' => 'Calendari', 'intro' => ''], ['heading' => 'Calendario', 'intro' => ''], ['heading' => 'Calendar', 'intro' => '']), $opts([])),
        ]);

        // "Més seccions": the other types of session 46.
        $slides = $slidesCa = $slidesEs = $slidesEn = [];

        foreach ([['carrusel-1.png', [24, 52, 104], [18, 120, 140], 'Comença el curs', 'Empieza el curso', 'The school year starts'], ['carrusel-2.png', [120, 60, 30], [220, 150, 60], 'Setmana de la ciència', 'Semana de la ciencia', 'Science week'], ['carrusel-3.png', [40, 100, 60], [140, 190, 90], 'Hort escolar', 'Huerto escolar', 'School garden']] as [$name, $from, $to, $ca, $es, $en]) {
            $slides[]   = ['image' => ['key' => $image($name, $from, $to), 'decorative' => true], 'link' => null];
            $slidesCa[] = ['image' => '', 'title' => $ca, 'text' => 'Una diapositiva de demostració.', 'link' => ''];
            $slidesEs[] = ['image' => '', 'title' => $es, 'text' => 'Una diapositiva de demostración.', 'link' => ''];
            $slidesEn[] = ['image' => '', 'title' => $en, 'text' => 'A demo slide.', 'link' => ''];
        }

        $people = $peopleCa = $peopleEs = $peopleEn = [];

        foreach ([['Marta', [240, 190, 160], 'L’escola ha fet créixer la curiositat dels nostres fills.', 'Mare d’alumnes'], ['Jordi', [170, 200, 240], 'Els projectes amb entitats del barri són una gran idea.', 'Veí del barri'], ['Aina', [200, 230, 180], 'Aquí vaig descobrir que m’agrada la ciència.', 'Antiga alumna']] as [$name, $color, $quote, $role]) {
            $people[]   = ['photo' => ['key' => $this->illustration($demo, 'testimoni-' . strtolower($name) . '.png', 240, 240, $color, $color, true), 'decorative' => true], 'name' => $name];
            $peopleCa[] = ['photo' => '', 'quote' => $quote, 'role' => $role];
            $peopleEs[] = ['photo' => '', 'quote' => $quote, 'role' => $role];
            $peopleEn[] = ['photo' => '', 'quote' => $quote, 'role' => $role];
        }

        $logos = $logosCa = [];

        foreach (['Ajuntament', 'Biblioteca', 'Centre cívic', 'Club esportiu', 'Escola de música'] as $i => $name) {
            $logos[]   = ['image' => ['key' => $image('logo-' . ($i + 1) . '.png', [60 + $i * 30, 90, 160 - $i * 20], [30, 50 + $i * 25, 90], 360, 160), 'decorative' => false], 'link' => null];
            $logosCa[] = ['image' => $name, 'link' => ''];
        }

        $gallery = db_connect()->tableExists('folders') && db_connect()->table('folders')->where('disk', 'public')->where('path', 'shared/Imatges web')->countAllResults() > 0;
        $more    = [
            new SectionData('pages.carousel', ['slides' => $slides, 'overlay' => 'dark', 'height' => 'm', 'button_style' => 'light', 'rotate' => false, 'interval' => 8], $tr(['label' => 'Novetats', 'slides' => $slidesCa], ['label' => 'Novedades', 'slides' => $slidesEs], ['label' => 'News', 'slides' => $slidesEn]), $opts(['background' => 'dark', 'spacing' => 's', 'width' => 'full'])),
            new SectionData('pages.testimonials', ['items' => $people, 'columns' => '3'], $tr(['heading' => 'Què en diuen', 'intro' => '', 'items' => $peopleCa], ['heading' => 'Qué dicen', 'intro' => '', 'items' => $peopleEs], ['heading' => 'What they say', 'intro' => '', 'items' => $peopleEn]), $opts(['background' => 'dark', 'spacing' => 'l'])),
            new SectionData('pages.logos', ['logos' => $logos, 'grayscale' => true], $tr(['heading' => 'Hi col·laboren', 'intro' => '', 'logos' => $logosCa], ['heading' => 'Colaboran', 'intro' => '', 'logos' => $logosCa], ['heading' => 'Partners', 'intro' => '', 'logos' => $logosCa]), $opts([])),
        ];

        if ($gallery) {
            $more[] = new SectionData('files.gallery', ['folder' => 'public:shared/Imatges web', 'columns' => '4', 'limit' => 24, 'order' => 'name', 'captions' => true], $tr(['heading' => 'Galeria', 'intro' => ''], ['heading' => 'Galería', 'intro' => ''], ['heading' => 'Gallery', 'intro' => '']), $opts(['style' => 'grid', 'background' => 'light']));
        }

        // Shown only once an administrator allows the host (Settings › Security); until then the
        // editor and the preview show the warning (D-46-01).
        $more[] = new SectionData('pages.embed', ['url' => 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', 'ratio' => '16x9', 'click_to_load' => true, 'fullscreen' => true], $tr(['heading' => 'Vídeo', 'intro' => '', 'title' => 'Vídeo de demostració'], ['heading' => 'Vídeo', 'intro' => '', 'title' => 'Vídeo de demostración'], ['heading' => 'Video', 'intro' => '', 'title' => 'Demo video']), $opts([]));

        $this->sectionPage($demo, $now, $author, 13, [
            'ca' => ['Més seccions', 'mes-seccions', 'Carrusel, testimonis, logotips, galeria i contingut incrustat.'],
            'es' => ['Más secciones', 'mas-secciones', 'Carrusel, testimonios, logotipos, galería y contenido incrustado.'],
            'en' => ['More sections', 'more-sections', 'Carousel, testimonials, logos, gallery and embedded content.'],
        ], $more);
    }

    /**
     * A published page in `sections` layout with its sections and first revision.
     *
     * @param array<string, array{0: string, 1: string, 2: string}> $texts locale => title, slug, summary
     * @param list<SectionData>                                     $sections
     */
    private function sectionPage(DemoData $demo, Time $now, ?int $author, int $position, array $texts, array $sections): int
    {
        $id = $this->page($demo, $now, $author, [
            'position' => $position, 'layout' => 'sections', 'record' => false,
            'texts'    => array_map(static fn (array $text): array => [$text[0], $text[1], $text[2], ''], $texts),
        ]);
        $repository = new PageSections();

        foreach ($sections as $section) {
            $demo->record('page_sections', $repository->save($id, $section));
        }

        foreach (db_connect()->table('page_section_translations')->select('id')->whereIn('section_id', array_map('intval', array_column(db_connect()->table('page_sections')->select('id')->where('page_id', $id)->get()->getResultArray(), 'id')))->get()->getResultArray() as $row) {
            $demo->record('page_section_translations', (int) $row['id']);
        }

        $revision = (new Revisions())->record($id, $author, 'create');

        if ($revision !== null) {
            $demo->record('page_revisions', $revision);
        }

        return $id;
    }

    /**
     * "Portada amb seccions": a page in `sections` layout with every section type of the module.
     */
    private function sectionsPage(DemoData $demo, Time $now, ?int $author, int $about): void
    {
        $contact = db_connect()->table('page_translations')->select('page_id')->where('locale', 'ca')->where('slug', 'contacte')->get()->getRowArray();
        $contact = is_array($contact) ? (string) $contact['page_id'] : null;
        $page    = static fn (?string $id, string $path): array => $id !== null ? ['type' => 'pages.page', 'value' => $id] : ['type' => 'url', 'value' => $path];
        $aboutLink   = $page((string) $about, '/qui-som');
        $contactLink = $page($contact, '/contacte');

        $id = $this->page($demo, $now, $author, [
            'position' => 4, 'layout' => 'sections', 'record' => false,
            'texts'    => [
                'ca' => ['Portada amb seccions', 'portada-seccions', 'Una portada feta amb seccions: bàner, enllaços, targetes, imatge i text i una crida a l’acció.', ''],
                'es' => ['Portada con secciones', 'portada-secciones', 'Una portada hecha con secciones.', ''],
                'en' => ['Home page with sections', 'sections-home', 'A home page built from sections.', ''],
            ],
        ]);

        $image = fn (string $name, int $width, int $height, array $from, array $to, bool $figure = false): string => $this->illustration($demo, $name, $width, $height, $from, $to, $figure);
        $tr    = static fn (array $ca, array $es, array $en): array => ['ca' => $ca, 'es' => $es, 'en' => $en];

        $sections = [];

        $sections[] = new SectionData('pages.hero', [
            'primary'         => $aboutLink,
            'primary_style'   => 'primary',
            'secondary'       => $contactLink,
            'secondary_style' => 'outline-light',
            'background'      => ['key' => $image('portada-fons.png', 1600, 700, [24, 52, 104], [18, 120, 140]), 'decorative' => true],
            'overlay'         => 'dark',
            'side_image'      => ['key' => $image('portada-figura.png', 520, 600, [0, 0, 0], [0, 0, 0], true), 'decorative' => true],
            'height'          => 'l',
            'align'           => 'start',
            'edge'            => 'wave',
        ], $tr(
            ['title' => 'Aprendre junts, créixer cada dia', 'text' => 'Un espai obert a les famílies, a l’alumnat i a tothom que vulgui conèixer-nos.', 'primary' => 'Qui som', 'secondary' => 'Contacte', 'background' => '', 'side_image' => ''],
            ['title' => 'Aprender juntos, crecer cada día', 'text' => 'Un espacio abierto a las familias, al alumnado y a todo el mundo.', 'primary' => 'Quiénes somos', 'secondary' => 'Contacto', 'background' => '', 'side_image' => ''],
            ['title' => 'Learning together, growing every day', 'text' => 'A place open to families, students and everyone who wants to know us.', 'primary' => 'About us', 'secondary' => 'Contact', 'background' => '', 'side_image' => ''],
        ), ['background' => 'dark', 'spacing' => 'm', 'width' => 'contained', 'style' => '', 'background_image' => '', 'anchor' => '']);

        $links = [
            ['bi-mortarboard', $aboutLink, 'Estudis', 'Estudios', 'Studies'],
            ['bi-calendar-event', ['type' => 'route', 'value' => 'events-public'], 'Calendari', 'Calendario', 'Calendar'],
            ['bi-newspaper', ['type' => 'route', 'value' => 'news-public'], 'Notícies', 'Noticias', 'News'],
            ['bi-book', $aboutLink, 'Biblioteca', 'Biblioteca', 'Library'],
            ['bi-pc-display', $contactLink, 'Tràmits', 'Trámites', 'Procedures'],
            ['bi-people', $aboutLink, 'Famílies', 'Familias', 'Families'],
            ['bi-briefcase', $aboutLink, 'Borsa de treball', 'Bolsa de trabajo', 'Jobs'],
            ['bi-envelope', $contactLink, 'Contacte', 'Contacto', 'Contact'],
        ];
        $values = $ca = $es = $en = [];

        foreach ($links as [$icon, $link, $labelCa, $labelEs, $labelEn]) {
            $values[] = ['icon' => $icon, 'image' => ['key' => '', 'decorative' => true], 'link' => $link];
            $ca[]     = ['label' => $labelCa, 'image' => ''];
            $es[]     = ['label' => $labelEs, 'image' => ''];
            $en[]     = ['label' => $labelEn, 'image' => ''];
        }

        $sections[] = new SectionData('pages.links', ['items' => $values, 'columns' => '4'], $tr(
            ['heading' => 'Accés ràpid', 'intro' => '', 'items' => $ca],
            ['heading' => 'Acceso rápido', 'intro' => '', 'items' => $es],
            ['heading' => 'Quick access', 'intro' => '', 'items' => $en],
        ), ['style' => 'icons', 'background' => 'none', 'spacing' => 'm', 'width' => 'contained', 'background_image' => '', 'anchor' => 'acces']);

        $tiles = [
            [[240, 200, 120], [232, 120, 80], 'Avisos i anuncis', 'Avisos y anuncios', 'Notices'],
            [[150, 210, 190], [40, 140, 140], 'Activitats', 'Actividades', 'Activities'],
            [[180, 190, 240], [90, 90, 200], 'Matrícula', 'Matrícula', 'Enrolment'],
            [[240, 180, 210], [190, 70, 120], 'Servei d’orientació', 'Servicio de orientación', 'Guidance'],
        ];
        $values = $ca = $es = $en = [];

        foreach ($tiles as $i => [$from, $to, $labelCa, $labelEs, $labelEn]) {
            $values[] = ['icon' => '', 'image' => ['key' => $image('rajola-' . ($i + 1) . '.png', 600, 450, $from, $to), 'decorative' => true], 'link' => $i === 1 ? ['type' => 'route', 'value' => 'events-public'] : $contactLink];
            $ca[]     = ['label' => $labelCa, 'image' => ''];
            $es[]     = ['label' => $labelEs, 'image' => ''];
            $en[]     = ['label' => $labelEn, 'image' => ''];
        }

        $sections[] = new SectionData('pages.links', ['items' => $values, 'columns' => '4'], $tr(
            ['heading' => '', 'intro' => '', 'items' => $ca],
            ['heading' => '', 'intro' => '', 'items' => $es],
            ['heading' => '', 'intro' => '', 'items' => $en],
        ), ['style' => 'tiles', 'background' => 'light', 'spacing' => 'm', 'width' => 'contained', 'background_image' => '', 'anchor' => '']);

        $cards = [
            [[120, 170, 230], [40, 90, 180], 'Projecte de lectura', 'Proyecto de lectura', 'Reading project', 'Clubs de lectura per a totes les edats i una biblioteca oberta a les tardes.', 'Clubes de lectura para todas las edades.', 'Reading clubs for every age and a library open in the afternoons.'],
            [[150, 220, 170], [30, 130, 90], 'Sortides i colònies', 'Salidas y colonias', 'Trips and camps', 'Aprenem fora de l’aula: natura, museus i teatre durant tot el curs.', 'Aprendemos fuera del aula durante todo el curso.', 'Learning outside the classroom all year round.'],
            [[245, 200, 140], [210, 110, 40], 'Tecnologia a l’aula', 'Tecnología en el aula', 'Technology in class', 'Robòtica, programació i creació digital amb projectes reals.', 'Robótica, programación y creación digital.', 'Robotics, coding and digital creation with real projects.'],
        ];
        $values = $ca = $es = $en = [];

        foreach ($cards as $i => [$from, $to, $titleCa, $titleEs, $titleEn, $textCa, $textEs, $textEn]) {
            $values[] = ['image' => ['key' => $image('targeta-' . ($i + 1) . '.png', 800, 500, $from, $to), 'decorative' => false], 'link' => $aboutLink];
            $ca[]     = ['image' => $titleCa, 'title' => $titleCa, 'text' => $textCa, 'link' => 'Més informació'];
            $es[]     = ['image' => $titleEs, 'title' => $titleEs, 'text' => $textEs, 'link' => 'Más información'];
            $en[]     = ['image' => $titleEn, 'title' => $titleEn, 'text' => $textEn, 'link' => 'Read more'];
        }

        $sections[] = new SectionData('pages.cards', ['cards' => $values, 'columns' => '3', 'button_style' => 'primary'], $tr(
            ['heading' => 'Projectes destacats', 'intro' => 'Algunes de les coses que fem durant el curs.', 'cards' => $ca],
            ['heading' => 'Proyectos destacados', 'intro' => 'Algunas de las cosas que hacemos durante el curso.', 'cards' => $es],
            ['heading' => 'Featured projects', 'intro' => 'Some of the things we do during the year.', 'cards' => $en],
        ), ['style' => '', 'background' => 'none', 'spacing' => 'l', 'width' => 'contained', 'background_image' => '', 'anchor' => 'projectes']);

        foreach ([['start', [200, 220, 250], [60, 100, 200], 'Un centre obert a l’entorn', 'Un centro abierto al entorno', 'A school open to its surroundings'], ['end', [250, 220, 190], [200, 120, 60], 'Famílies que participen', 'Familias que participan', 'Families who take part']] as $i => [$side, $from, $to, $titleCa, $titleEs, $titleEn]) {
            $sections[] = new SectionData('pages.media_text', [
                'image'        => ['key' => $image('imatge-text-' . ($i + 1) . '.png', 800, 600, $from, $to), 'decorative' => false],
                'image_side'   => $side,
                'link'         => $i === 0 ? $aboutLink : $contactLink,
                'button_style' => 'primary',
            ], $tr(
                ['image' => $titleCa, 'title' => $titleCa, 'text' => HtmlSanitizer::clean('<p>Treballem amb entitats del barri, empreses i institucions perquè l’aprenentatge tingui sentit <strong>fora de l’aula</strong>.</p>'), 'link' => $i === 0 ? 'Coneix-nos' : 'Escriu-nos'],
                ['image' => $titleEs, 'title' => $titleEs, 'text' => HtmlSanitizer::clean('<p>Trabajamos con entidades del barrio, empresas e instituciones.</p>'), 'link' => $i === 0 ? 'Conócenos' : 'Escríbenos'],
                ['image' => $titleEn, 'title' => $titleEn, 'text' => HtmlSanitizer::clean('<p>We work with local groups, companies and institutions so that learning makes sense <strong>outside the classroom</strong>.</p>'), 'link' => $i === 0 ? 'About us' : 'Write to us'],
            ), ['style' => '', 'background' => $i === 0 ? 'none' : 'light', 'spacing' => 'm', 'width' => 'contained', 'background_image' => '', 'anchor' => '']);
        }

        $sections[] = new SectionData('pages.cta', ['link' => $contactLink, 'button_style' => 'light'], $tr(
            ['title' => 'Vols venir a conèixer-nos?', 'text' => 'Fem visites guiades cada mes. Escriu-nos i et direm quan és la propera.', 'link' => 'Demana una visita'],
            ['title' => '¿Quieres venir a conocernos?', 'text' => 'Hacemos visitas guiadas cada mes.', 'link' => 'Pide una visita'],
            ['title' => 'Would you like to visit us?', 'text' => 'We run guided visits every month. Write to us and we will tell you when the next one is.', 'link' => 'Ask for a visit'],
        ), ['style' => '', 'background' => 'primary', 'spacing' => 'l', 'width' => 'contained', 'background_image' => '', 'anchor' => 'visita']);

        $sections[] = new SectionData('pages.text', ['column' => 'narrow'], $tr(
            ['heading' => 'Sobre aquesta pàgina', 'text' => HtmlSanitizer::clean('<p>Aquesta pàgina s’ha fet amb l’<strong>editor de seccions</strong>: cada bloc és una secció que s’omple amb un formulari i es pot moure, amagar o duplicar.</p>')],
            ['heading' => 'Sobre esta página', 'text' => HtmlSanitizer::clean('<p>Esta página se ha hecho con el <strong>editor de secciones</strong>.</p>')],
            ['heading' => 'About this page', 'text' => HtmlSanitizer::clean('<p>This page was built with the <strong>section editor</strong>: every block is a section filled in with a form, which can be moved, hidden or duplicated.</p>')],
        ), ['style' => '', 'background' => 'none', 'spacing' => 'm', 'width' => 'contained', 'background_image' => '', 'anchor' => '']);

        $repository = new PageSections();

        foreach ($sections as $section) {
            $demo->record('page_sections', $repository->save($id, $section));
        }

        foreach (db_connect()->table('page_section_translations')->select('id')->whereIn('section_id', array_map('intval', array_column(db_connect()->table('page_sections')->select('id')->where('page_id', $id)->get()->getResultArray(), 'id')))->get()->getResultArray() as $row) {
            $demo->record('page_section_translations', (int) $row['id']);
        }

        $revision = (new Revisions())->record($id, $author, 'create');

        if ($revision !== null) {
            $demo->record('page_revisions', $revision);
        }
    }

    /**
     * A placeholder illustration (PNG drawn with GD: a gradient with soft shapes, or a simple
     * figure on a transparent background) stored on the public disk, folder `modules/pages`.
     *
     * @param array{0: int, 1: int, 2: int} $from
     * @param array{0: int, 1: int, 2: int} $to
     */
    private function illustration(DemoData $demo, string $name, int $width, int $height, array $from, array $to, bool $figure = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, true);

        if ($figure) {
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
            $skin  = (int) imagecolorallocate($image, 246, 206, 170);
            $hair  = (int) imagecolorallocate($image, 92, 60, 40);
            $shirt = (int) imagecolorallocate($image, 70, 130, 220);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.88), (int) ($width * 0.8), (int) ($height * 0.6), $shirt);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.3), (int) ($width * 0.38), (int) ($width * 0.44), $hair);
            imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.33), (int) ($width * 0.32), (int) ($width * 0.38), $skin);
            imagefilledrectangle($image, 0, (int) ($height * 0.98), $width, $height, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            for ($y = 0; $y < $height; $y++) {
                $t     = $y / max(1, $height - 1);
                $color = (int) imagecolorallocate($image, (int) ($from[0] + ($to[0] - $from[0]) * $t), (int) ($from[1] + ($to[1] - $from[1]) * $t), (int) ($from[2] + ($to[2] - $from[2]) * $t));
                imageline($image, 0, $y, $width, $y, $color);
            }

            $seed = crc32($name);

            for ($i = 0; $i < 7; $i++) {
                $size  = (int) (min($width, $height) * (0.18 + (($seed >> $i) & 7) / 20));
                $x     = (int) ($width * ((($seed >> ($i * 3)) & 15) / 15));
                $y     = (int) ($height * ((($seed >> ($i * 2 + 1)) & 15) / 15));
                $light = (int) imagecolorallocatealpha($image, 255, 255, 255, 96 + ($i * 4));
                imagefilledellipse($image, $x, $y, $size, $size, $light);
            }
        }

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();

        /** @var Storage $storage */
        $storage = service('storage');
        $stored  = $storage->storeContents($contents, $name, 'public', 'modules/pages');
        $demo->record('stored_files', $stored->id);

        return $stored->key;
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
            'layout'     => $page['layout'] ?? 'article',
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

        if ($page['record'] ?? true) {
            $revision = (new Revisions())->record($id, $author, 'create');

            if ($revision !== null) {
                $demo->record('page_revisions', $revision);
            }
        }

        return $id;
    }

    private function userId(string $email): ?int
    {
        $row = db_connect()->table('auth_identities')->select('user_id')->where('type', 'email_password')->where('secret', $email)->get()->getRowArray();

        return is_array($row) ? (int) $row['user_id'] : null;
    }
}
