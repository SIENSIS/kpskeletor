<?php

declare(strict_types=1);

namespace Modules\Events\Database\Seeds;

use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;

/**
 * Demo data of the Events module, in Catalan only (`php spark modules:demo Events`): three
 * categories and events around today: public ones (also on the public site), a private weekly
 * meeting with a skipped date, an all-day event, a monthly event, a cancelled one, one restricted
 * to the `editor` group and one restricted to the demo user usuari@example.com only (seen by
 * nobody else, administrators included). Every row goes through `$demo`.
 */
final class EventsDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $zone   = new DateTimeZone(site_timezone());
        $today  = new DateTimeImmutable('today', $zone);
        $admin  = $this->userId('admin@example.com');
        $user   = $this->userId('usuari@example.com');
        $editor = db_connect()->table('groups')->select('id')->where('name', 'editor')->get()->getRowArray();

        $institutional = $this->category($demo, 'institucional', 'Institucional', '#0d6efd', 1);
        $activities    = $this->category($demo, 'activitats', 'Activitats', '#198754', 2);
        $training      = $this->category($demo, 'formacio', 'Formació', '#fd7e14', 3);

        $tuesday = $today->modify('monday this week')->modify('+1 day');

        $events = [
            [
                'title' => 'Jornada de portes obertes', 'category' => $activities, 'visibility' => 'public',
                'start' => $today->modify('+3 days')->setTime(10, 0), 'end' => $today->modify('+3 days')->setTime(13, 0),
                'location' => 'Sala d’actes', 'organiser' => 'Secretaria',
                'description' => '<p>Visita guiada a les instal·lacions i presentació dels projectes del curs.</p>',
            ],
            [
                'title' => 'Curs d’introducció a la ciberseguretat', 'category' => $training, 'visibility' => 'public',
                'start' => $today->modify('+8 days')->setTime(16, 0), 'end' => $today->modify('+8 days')->setTime(18, 0),
                'location' => 'Aula 2', 'url' => 'https://example.com/inscripcio',
                'description' => '<p>Contrasenyes, doble factor i pesca electrònica.</p>',
            ],
            [
                'title' => 'Festa major', 'category' => $activities, 'visibility' => 'public', 'all_day' => 1,
                'start' => $today->modify('+12 days'), 'end' => $today->modify('+14 days'),
            ],
            [
                'title' => 'Reunió de coordinació', 'category' => $institutional, 'visibility' => 'private',
                'start' => $tuesday->modify('-14 days')->setTime(9, 0), 'end' => $tuesday->modify('-14 days')->setTime(10, 0),
                'location' => 'Sala de reunions', 'recur_freq' => 'weekly', 'recur_days' => 'tu', 'recur_count' => 20,
                'recur_exceptions' => $tuesday->modify('+14 days')->format('Y-m-d'),
            ],
            [
                'title' => 'Claustre mensual', 'category' => $institutional, 'visibility' => 'private',
                'start' => $today->modify('first day of this month')->modify('+4 days')->setTime(17, 0),
                'end' => $today->modify('first day of this month')->modify('+4 days')->setTime(19, 0),
                'recur_freq' => 'monthly', 'recur_until' => $today->modify('+1 year')->format('Y-m-d'),
            ],
            [
                'title' => 'Sortida al museu (anul·lada)', 'category' => $activities, 'visibility' => 'private', 'status' => 'cancelled',
                'start' => $today->modify('+5 days')->setTime(9, 30), 'end' => $today->modify('+5 days')->setTime(14, 0),
            ],
            [
                'title' => 'Revisió de continguts del web', 'category' => $institutional, 'visibility' => 'restricted',
                'start' => $today->modify('+2 days')->setTime(12, 0), 'end' => $today->modify('+2 days')->setTime(13, 0),
                'groups' => is_array($editor) ? [(int) $editor['id']] : [],
                'description' => '<p>Només per al grup d’editors.</p>',
            ],
            [
                'title' => 'Entrevista personal', 'category' => $institutional, 'visibility' => 'restricted',
                'start' => $today->modify('+1 day')->setTime(11, 0), 'end' => $today->modify('+1 day')->setTime(11, 30),
                'users' => $user === null ? [] : [$user],
                'description' => '<p>Només la veu l’usuari usuari@example.com.</p>',
            ],
        ];

        foreach ($events as $event) {
            $this->event($demo, $event, $admin);
        }
    }

    private function category(DemoData $demo, string $slug, string $name, string $color, int $position): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('event_categories', ['slug' => $slug, 'color' => $color, 'position' => $position, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $demo->insert('event_category_translations', ['category_id' => $id, 'locale' => 'ca', 'name' => $name, 'description' => null]);

        return $id;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function event(DemoData $demo, array $event, ?int $author): void
    {
        $storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $now     = Time::now()->toDateTimeString();
        $start   = $event['start'];
        $end     = $event['end'] ?? null;
        \assert($start instanceof DateTimeImmutable);

        $id = (int) $demo->insert('events', [
            'category_id'      => $event['category'],
            'starts_at'        => $start->setTimezone($storage)->format('Y-m-d H:i:s'),
            'ends_at'          => $end instanceof DateTimeImmutable ? $end->setTimezone($storage)->format('Y-m-d H:i:s') : null,
            'all_day'          => $event['all_day'] ?? 0,
            'location'         => $event['location'] ?? null,
            'organiser'        => $event['organiser'] ?? null,
            'url'              => $event['url'] ?? null,
            'visibility'       => $event['visibility'],
            'status'           => $event['status'] ?? 'confirmed',
            'recur_freq'       => $event['recur_freq'] ?? null,
            'recur_interval'   => 1,
            'recur_days'       => $event['recur_days'] ?? null,
            'recur_until'      => $event['recur_until'] ?? null,
            'recur_count'      => $event['recur_count'] ?? null,
            'recur_exceptions' => $event['recur_exceptions'] ?? null,
            'sequence'         => 0,
            'created_by'       => $author,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $demo->insert('event_translations', [
            'event_id' => $id, 'locale' => 'ca', 'title' => $event['title'], 'description' => $event['description'] ?? null,
        ]);

        foreach ($event['groups'] ?? [] as $group) {
            $demo->insert('event_audience', ['event_id' => $id, 'group_id' => $group, 'user_id' => null]);
        }

        foreach ($event['users'] ?? [] as $user) {
            $demo->insert('event_audience', ['event_id' => $id, 'group_id' => null, 'user_id' => $user]);
        }
    }

    private function userId(string $email): ?int
    {
        $row = db_connect()->table('auth_identities')->select('user_id')->where('type', 'email_password')->where('secret', $email)->get()->getRowArray();

        return is_array($row) ? (int) $row['user_id'] : null;
    }
}
