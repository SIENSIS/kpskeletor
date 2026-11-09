<?php

declare(strict_types=1);

namespace Modules\News\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\News\Services\BodySanitizer;

/**
 * Demo data of the News module, in Catalan only (`php spark modules:demo News`): two categories,
 * public articles (public site and RSS), a private article, a pinned critical notice for every
 * signed-in user, a warning notice for the `editor` group, a scheduled article and a draft.
 * Authors are the Auth demo users when they exist. Every row goes through `$demo`.
 */
final class NewsDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $now    = Time::now();
        $admin  = $this->userId('admin@example.com');
        $editor = $this->userId('editor@example.com') ?? $admin;

        $institutional = $this->category($demo, 'institucional', 'Institucional', 'Comunicats i novetats de l’organització.', 1);
        $activities    = $this->category($demo, 'activitats', 'Activitats', 'Jornades, cursos i sortides.', 2);

        $posts = [
            [
                'slug' => 'benvinguda-al-nou-portal', 'category' => $institutional, 'author' => $admin,
                'title' => 'Benvinguda al nou portal', 'summary' => 'Estrenem un portal més ràpid, accessible i segur.',
                'body' => '<p>Avui posem en marxa el <strong>nou portal</strong>. Hi trobareu les notícies, el calendari i els vostres tràmits.</p><ul><li>Disseny adaptat al mòbil</li><li>Mode fosc</li><li>Accés amb doble factor</li></ul>',
                'visibility' => 'public', 'published' => $now->subDays(6),
            ],
            [
                'slug' => 'jornada-de-portes-obertes', 'category' => $activities, 'author' => $editor,
                'title' => 'Jornada de portes obertes', 'summary' => 'Dissabte us ensenyem les instal·lacions i els projectes del curs.',
                'body' => '<p>La jornada començarà a les 10 h a la sala d’actes. Podeu inscriure-us a la <a href="https://example.com/inscripcio">pàgina d’inscripció</a>.</p>',
                'visibility' => 'public', 'published' => $now->subDays(3),
            ],
            [
                'slug' => 'nou-curs-de-ciberseguretat', 'category' => $activities, 'author' => $editor,
                'title' => 'Nou curs de ciberseguretat', 'summary' => 'Inscripcions obertes per al curs d’introducció a la ciberseguretat.',
                'body' => '<h2>Continguts</h2><ol><li>Contrasenyes i gestors</li><li>Doble factor</li><li>Pesca electrònica</li></ol>',
                'visibility' => 'public', 'published' => $now->subDays(1),
            ],
            [
                'slug' => 'calendari-intern-de-reunions', 'category' => $institutional, 'author' => $admin,
                'title' => 'Calendari intern de reunions', 'summary' => 'Les reunions de coordinació passen a ser els dimarts.',
                'body' => '<p>A partir de la setmana vinent, les reunions de coordinació es faran els <em>dimarts a les 9 h</em>.</p>',
                'visibility' => 'private', 'published' => $now->subDays(2),
            ],
            [
                'slug' => 'tall-del-servei-dissabte', 'category' => $institutional, 'author' => $admin, 'type' => 'notice',
                'priority' => 'critical', 'pinned' => 1,
                'title' => 'Tall del servei dissabte', 'summary' => 'El portal no estarà disponible dissabte de 8 a 10 h per manteniment.',
                'body' => '<p>Desem la feina abans de dissabte a les 8 h. Durant el manteniment no es podrà iniciar la sessió.</p>',
                'visibility' => 'private', 'published' => $now->subHours(5), 'expires' => $now->addDays(5),
            ],
            [
                'slug' => 'revisio-dels-continguts-del-web', 'category' => $institutional, 'author' => $admin, 'type' => 'notice',
                'priority' => 'warning', 'groups' => ['editor'],
                'title' => 'Revisió dels continguts del web', 'summary' => 'Els editors han de revisar les seves publicacions abans de divendres.',
                'body' => '<p>Reviseu els textos, les imatges de portada i les categories de les vostres publicacions.</p>',
                'visibility' => 'groups', 'published' => $now->subHours(20), 'expires' => $now->addDays(10),
            ],
            [
                'slug' => 'horari-d-estiu', 'category' => $institutional, 'author' => $admin, 'type' => 'notice',
                'priority' => 'info',
                'title' => 'Horari d’estiu', 'summary' => 'Del juliol a l’agost, l’horari d’atenció és de 8 a 15 h.',
                'body' => '<p>L’horari d’estiu s’aplica a l’atenció presencial i telefònica.</p>',
                'visibility' => 'private', 'published' => $now->subDays(4),
            ],
            [
                'slug' => 'resultats-de-l-enquesta', 'category' => $activities, 'author' => $editor,
                'title' => 'Resultats de l’enquesta', 'summary' => 'Es publicaran la setmana vinent.',
                'body' => '<p>Gràcies a totes les persones que hi han participat.</p>',
                'visibility' => 'public', 'published' => $now->addDays(7), 'status' => 'scheduled',
            ],
            [
                'slug' => 'esborrany-memoria-anual', 'category' => $institutional, 'author' => $editor,
                'title' => 'Esborrany de la memòria anual', 'summary' => 'Primer esborrany per revisar.',
                'body' => '<p>Text pendent de revisió.</p>',
                'visibility' => 'private', 'published' => null, 'status' => 'draft',
            ],
        ];

        foreach ($posts as $post) {
            $this->post($demo, $post, $now);
        }
    }

    private function category(DemoData $demo, string $slug, string $name, string $description, int $position): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('news_categories', ['slug' => $slug, 'position' => $position, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $demo->insert('news_category_translations', ['category_id' => $id, 'locale' => 'ca', 'name' => $name, 'description' => $description]);

        return $id;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(DemoData $demo, array $post, Time $now): void
    {
        $published = $post['published'] instanceof Time ? $post['published'] : null;
        $expires   = ($post['expires'] ?? null) instanceof Time ? $post['expires'] : null;

        $id = (int) $demo->insert('news', [
            'category_id'  => $post['category'],
            'slug'         => $post['slug'],
            'type'         => $post['type'] ?? 'article',
            'priority'     => $post['priority'] ?? 'info',
            'pinned'       => $post['pinned'] ?? 0,
            'visibility'   => $post['visibility'],
            'status'       => $post['status'] ?? 'published',
            'published_at' => $published?->toDateTimeString(),
            'expires_at'   => $expires?->toDateTimeString(),
            'author_id'    => $post['author'],
            // Demo notices do not notify anybody.
            'notified_at'  => ($post['type'] ?? 'article') === 'notice' ? $now->toDateTimeString() : null,
            'created_at'   => $now->toDateTimeString(),
            'updated_at'   => $now->toDateTimeString(),
        ]);

        $demo->insert('news_translations', [
            'news_id' => $id,
            'locale'  => 'ca',
            'title'   => $post['title'],
            'summary' => $post['summary'],
            'body'    => BodySanitizer::clean((string) $post['body']),
        ]);

        foreach ($post['groups'] ?? [] as $group) {
            $row = db_connect()->table('groups')->select('id')->where('name', $group)->get()->getRowArray();

            if (is_array($row)) {
                $demo->insert('news_groups', ['news_id' => $id, 'group_id' => (int) $row['id']]);
            }
        }
    }

    private function userId(string $email): ?int
    {
        $row = db_connect()->table('auth_identities')->select('user_id')->where('type', 'email_password')->where('secret', $email)->get()->getRowArray();

        return is_array($row) ? (int) $row['user_id'] : null;
    }
}
