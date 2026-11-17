<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Seeds;

use CodeIgniter\I18n\Time;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;
use Modules\Tasks\Calendar\DueDates;

/**
 * Demo data of the Tasks tutorial module (`php spark modules:demo Tasks`, after Auth's): two
 * projects (names in Catalan, Spanish and English), to-do tasks in every column of the board
 * (public ones on the public page, one overdue for the reminder, one done today for the
 * activity chart) assigned to the Auth demo users, and a request from the public form. Due dates
 * are published to the calendar `tasks.due`. Every row goes through `$demo`.
 */
final class TasksDemoSeeder extends DemoSeeder
{
    public function load(DemoData $demo): void
    {
        $now    = Time::now();
        $admin  = $this->userId('admin@example.com');
        $editor = $this->userId('editor@example.com') ?? $admin;
        $user   = $this->userId('usuari@example.com') ?? $editor;

        $web = $this->project($demo, 'nou-web', '#0d6efd', $now->addDays(30)->toDateString(), [
            'ca' => ['Nou web', 'Redisseny del web públic i migració dels continguts.'],
            'es' => ['Nueva web', 'Rediseño de la web pública y migración de los contenidos.'],
            'en' => ['New website', 'Redesign of the public website and content migration.'],
        ]);
        $event = $this->project($demo, 'jornada-de-portes-obertes', '#198754', $now->addDays(12)->toDateString(), [
            'ca' => ['Jornada de portes obertes', 'Organització de la jornada de dissabte.'],
            'es' => ['Jornada de puertas abiertas', 'Organización de la jornada del sábado.'],
            'en' => ['Open day', 'Organising Saturday\'s open day.'],
        ]);

        $tasks = [
            ['project' => $web, 'title' => 'Definir el mapa del web', 'status' => 'done', 'priority' => 'high', 'labels' => ['design'], 'due' => $now->subDays(5), 'done' => $now, 'assignees' => [$editor], 'visibility' => 'public', 'by' => $admin],
            ['project' => $web, 'title' => 'Maquetar la pàgina d’inici', 'status' => 'doing', 'priority' => 'high', 'labels' => ['design', 'feature'], 'due' => $now->addDays(3), 'assignees' => [$editor, $user], 'visibility' => 'public', 'by' => $admin, 'estimate' => '12.50'],
            ['project' => $web, 'title' => 'Migrar les notícies antigues', 'status' => 'todo', 'priority' => 'normal', 'labels' => ['docs'], 'due' => $now->addDays(10), 'assignees' => [$user], 'visibility' => 'public', 'by' => $admin, 'estimate' => '6'],
            ['project' => $web, 'title' => 'Revisar l’accessibilitat dels formularis', 'status' => 'todo', 'priority' => 'normal', 'labels' => ['bug'], 'due' => $now->subDays(2), 'assignees' => [$user], 'visibility' => 'private', 'by' => $editor],
            ['project' => $event, 'title' => 'Reservar la sala d’actes', 'status' => 'done', 'priority' => 'normal', 'labels' => ['support'], 'due' => $now->subDays(1), 'done' => $now->subDays(1), 'assignees' => [$admin], 'visibility' => 'private', 'by' => $admin],
            ['project' => $event, 'title' => 'Preparar el cartell', 'status' => 'doing', 'priority' => 'low', 'labels' => ['design'], 'due' => $now->addDays(5), 'assignees' => [$editor], 'visibility' => 'public', 'by' => $admin],
            ['project' => $event, 'title' => 'Enviar la convocatòria', 'status' => 'todo', 'priority' => 'high', 'labels' => [], 'due' => $now->addDays(7), 'assignees' => [$admin, $editor], 'visibility' => 'private', 'by' => $admin],
            ['project' => null, 'title' => 'Renovar el certificat del servidor', 'status' => 'todo', 'priority' => 'normal', 'labels' => ['support'], 'due' => null, 'assignees' => [], 'visibility' => 'private', 'by' => $admin],
        ];

        foreach ($tasks as $index => $task) {
            $created = $now->subDays(12 - $index);
            $id      = (int) $demo->insert('tasks', [
                'project_id'     => $task['project'],
                'title'          => $task['title'],
                'description'    => null,
                'status'         => $task['status'],
                'priority'       => $task['priority'],
                'labels'         => $task['labels'] === [] ? null : json_encode($task['labels']),
                'due_on'         => $task['due']?->toDateString(),
                'estimate_hours' => $task['estimate'] ?? null,
                'visibility'     => $task['visibility'],
                'created_by'     => $task['by'],
                'completed_at'   => isset($task['done']) ? $task['done']->toDateTimeString() : null,
                'created_at'     => $created->toDateTimeString(),
                'updated_at'     => $created->toDateTimeString(),
            ]);

            foreach (array_unique(array_filter($task['assignees'])) as $assignee) {
                $demo->insert('task_assignees', ['task_id' => $id, 'user_id' => $assignee]);
            }

            DueDates::sync($id);
        }

        $demo->insert('task_requests', [
            'name'       => 'Marta Riera',
            'email'      => 'marta@example.com',
            'title'      => 'Afegir el calendari d’activitats al web',
            'details'    => 'Estaria bé poder consultar les activitats del trimestre des del web públic.',
            'locale'     => 'ca',
            'status'     => 'new',
            'created_at' => $now->subHours(3)->toDateTimeString(),
            'updated_at' => $now->subHours(3)->toDateTimeString(),
        ]);
    }

    /**
     * @param array<string, array{0: string, 1: string}> $texts locale => [name, description]
     */
    private function project(DemoData $demo, string $slug, string $color, string $endsOn, array $texts): int
    {
        $now = Time::now()->toDateTimeString();
        $id  = (int) $demo->insert('task_projects', ['slug' => $slug, 'color' => $color, 'ends_on' => $endsOn, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        foreach ($texts as $locale => [$name, $description]) {
            $demo->insert('task_project_translations', ['project_id' => $id, 'locale' => $locale, 'name' => $name, 'description' => $description]);
        }

        return $id;
    }

    private function userId(string $email): ?int
    {
        $row = db_connect()->table('auth_identities')->select('user_id')->where('type', 'email_password')->where('secret', $email)->get()->getRowArray();

        return is_array($row) ? (int) $row['user_id'] : null;
    }
}
