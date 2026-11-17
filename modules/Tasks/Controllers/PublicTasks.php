<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Controllers\FrontendController;
use Modules\Tasks\Services\TaskRepository;

/**
 * The public page of the to-do tasks (tutorial 5): **only `public` to-do tasks** (not in the trash,
 * in an active project or none), grouped by status, whoever is signed in.
 *
 * - `GET tasks`: every public to-do task, with the projects as filters;
 * - `GET tasks/project/{slug}`: those of one active project (anything else is a 404).
 *
 * SEO metadata through `FrontendController::meta()`; the menu link type `ProjectLink` points here.
 */
final class PublicTasks extends FrontendController
{
    public function index(): string
    {
        $repository = new TaskRepository();

        $this->meta([
            'description' => lang('Tasks.public.description'),
            'canonical'   => site_url('tasks'),
            'og'          => true,
        ]);

        return $this->render('Modules\Tasks\Views\public\index', [
            'title'       => lang('Tasks.public.title'),
            'intro'       => lang('Tasks.public.description'),
            'columns'     => $this->columns($repository->publicTasks()),
            'projects'    => $repository->publicProjects(),
            'current'     => null,
            'requestUrl'  => (bool) setting('Tasks.publicRequests') ? site_url('tasks/request') : null,
            'breadcrumbs' => [],
        ]);
    }

    public function project(string $slug): string
    {
        $repository = new TaskRepository();
        $project    = $repository->activeProject($slug);

        if ($project === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->meta([
            'description' => $project['description'] !== '' ? mb_substr($project['description'], 0, 300) : lang('Tasks.public.description'),
            'canonical'   => site_url('tasks/project/' . rawurlencode($project['slug'])),
            'og'          => true,
        ]);

        return $this->render('Modules\Tasks\Views\public\index', [
            'title'       => $project['name'],
            'intro'       => $project['description'],
            'columns'     => $this->columns($repository->publicTasks($project['id'])),
            'projects'    => $repository->publicProjects(),
            'current'     => $project['slug'],
            'requestUrl'  => (bool) setting('Tasks.publicRequests') ? site_url('tasks/request') : null,
            'breadcrumbs' => [['label' => lang('Tasks.public.title'), 'url' => site_url('tasks')], ['label' => $project['name']]],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function columns(array $rows): array
    {
        $columns = array_fill_keys(TaskRepository::STATUSES, []);

        foreach ($rows as $row) {
            $status             = in_array($row['status'], TaskRepository::STATUSES, true) ? (string) $row['status'] : 'todo';
            $columns[$status][] = [
                'title'       => (string) $row['title'],
                'description' => (string) ($row['description'] ?? ''),
                'due'         => is_string($row['due_on']) ? $row['due_on'] : null,
                'labels'      => TaskRepository::labels($row['labels']),
            ];
        }

        return $columns;
    }
}
