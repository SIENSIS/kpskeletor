<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Tasks\Services\TaskRepository;

/**
 * The board (tutorial 3, resource `tasks-board`, `tasks-board.view`, every group): the to-do
 * tasks in three columns (to do, doing, done). Users with `tasks-tasks.list` see every to-do task,
 * the others only those assigned to them (`TaskRepository::board()`).
 *
 * - `GET backend/tasks` (`?project=<id>`): the page, drawn on the server (works without
 *   JavaScript); `Assets/js/board.js` (`data-component="tasks/board"`) adds drag and drop and the
 *   keyboard "move" buttons;
 * - `GET backend/tasks/board/data` (`?project=`): the same columns as JSON (the component reloads
 *   them after a move);
 * - `POST backend/tasks/board/{code}/move` (`status`, JSON or form): moves a to-do task. Allowed
 *   with `tasks-tasks.edit` or to an assignee (`TaskRepository::canMove()`); anything the user may
 *   not see is a 404, may not move a 403. Logged in the activity log.
 */
final class Board extends BackendController
{
    public function index(): string
    {
        $user    = $this->currentUser();
        $project = $this->project();

        return $this->render('Modules\Tasks\Views\board\index', [
            'title'    => lang('Tasks.boardTitle'),
            'icon'     => 'bi bi-kanban',
            'columns'  => $this->columns($user, $project),
            'projects' => (new TaskRepository())->projectNames(true),
            'project'  => $project,
            'seesAll'  => TaskRepository::seesAll($user),
            'canAdd'   => $this->can('tasks-tasks.add'),
            'options'  => [
                'dataUrl' => backend_url('tasks/board/data') . ($project !== null ? '?project=' . $project : ''),
                'css'     => asset('tasks::css/tasks.css'),
                'texts'   => [
                    'moved'      => lang('Tasks.board.moved'),
                    'moveTo'     => lang('Tasks.board.moveTo'),
                    'move'       => lang('Tasks.board.move'),
                    'priorities' => Tasks::options('priorities', TaskRepository::PRIORITIES),
                    'empty'      => lang('Tasks.board.emptyColumn'),
                    'statuses'   => Tasks::options('statuses', TaskRepository::STATUSES),
                ],
            ],
        ]);
    }

    public function data(): ResponseInterface
    {
        return $this->response->setJSON(['columns' => $this->columns($this->currentUser(), $this->project())]);
    }

    public function move(string $code): ResponseInterface
    {
        $user       = $this->currentUser();
        $repository = new TaskRepository();
        $id         = Crud::unmaskId($code, 'tasks');
        $task       = $id === null ? null : $repository->find((int) $id);
        $json       = $this->wantsJson();

        if ($task === null || ! ($repository->canSee($user, $task) || TaskRepository::seesAll($user))) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $repository->canMove($user, $task)) {
            security_log('Tasks', 'move_refused', ['resource' => 'tasks-board', 'record_id' => (int) $task['id']]);

            return $json
                ? $this->response->setStatusCode(403)->setJSON(['message' => lang('Tasks.board.forbidden')])
                : redirect()->to(backend_url('tasks'))->with('error', lang('Tasks.board.forbidden'));
        }

        $status = $this->postedStatus();

        if ($status === null) {
            return $json
                ? $this->response->setStatusCode(422)->setJSON(['message' => lang('Tasks.board.invalidStatus')])
                : redirect()->to(backend_url('tasks'))->with('error', lang('Tasks.board.invalidStatus'));
        }

        if ($status !== $task['status']) {
            $repository->setStatus((int) $task['id'], $status);

            activity_log('Tasks', 'moved', [
                'resource'  => 'tasks-tasks',
                'record_id' => (int) $task['id'],
                'summary'   => (string) $task['title'],
                'changes'   => ['status' => [(string) $task['status'], $status]],
            ]);
        }

        return $json
            ? $this->response->setJSON(['id' => $code, 'status' => $status, 'message' => lang('Tasks.board.moved')])
            : redirect()->to(backend_url('tasks'))->with('success', lang('Tasks.board.moved'));
    }

    /**
     * The board's columns for a user: status => list of cards.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function columns(User $user, ?int $project): array
    {
        $repository = new TaskRepository();
        $projects   = $repository->projectNames();
        $users      = [];
        $rows       = $repository->board($user, $project);
        $columns    = array_fill_keys(TaskRepository::STATUSES, []);
        $editAll    = $this->can('tasks-tasks.edit');
        $ids        = array_unique(array_merge(...array_map(static fn (array $row): array => $row['assignees'], $rows ?: [['assignees' => []]])));

        if ($ids !== []) {
            foreach (db_connect()->table('users')->select('id, username')->whereIn('id', $ids)->get()->getResultArray() as $row) {
                $users[(int) $row['id']] = (string) $row['username'];
            }
        }

        foreach ($rows as $row) {
            $status = in_array($row['status'], TaskRepository::STATUSES, true) ? (string) $row['status'] : 'todo';
            $code   = Crud::maskId((int) $row['id'], 'tasks');
            $due    = is_string($row['due_on']) ? $row['due_on'] : null;

            $columns[$status][] = [
                'id'        => $code,
                'title'     => (string) $row['title'],
                'priority'  => (string) $row['priority'],
                'project'   => $row['project_id'] !== null ? ($projects[(int) $row['project_id']] ?? null) : null,
                'due'       => $due,
                'overdue'   => $due !== null && $status !== 'done' && $due < date('Y-m-d'),
                'labels'    => array_map(static fn (string $label): string => lang('Tasks.labels.' . $label), TaskRepository::labels($row['labels'])),
                'assignees' => array_values(array_map(static fn (int $id): string => $users[$id] ?? '#' . $id, $row['assignees'])),
                'canMove'   => $editAll || in_array((int) $user->id, $row['assignees'], true),
                'moveUrl'   => backend_url('tasks/board/' . $code . '/move'),
                'url'       => $this->can('tasks-tasks.show') ? backend_url('tasks/tasks/show/' . $code) : null,
            ];
        }

        return $columns;
    }

    private function project(): ?int
    {
        $value = $this->request->getGet('project');

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function postedStatus(): ?string
    {
        $request = $this->request;
        $status  = null;

        if ($request instanceof IncomingRequest) {
            $json   = str_contains($request->getHeaderLine('Content-Type'), 'json') ? $request->getJSON(true) : null;
            $status = is_array($json) ? ($json['status'] ?? null) : $request->getPost('status');
        }

        return is_string($status) && in_array($status, TaskRepository::STATUSES, true) ? $status : null;
    }

    private function wantsJson(): bool
    {
        return str_contains($this->request->getHeaderLine('Accept'), 'json') || $this->request->isAJAX();
    }
}
