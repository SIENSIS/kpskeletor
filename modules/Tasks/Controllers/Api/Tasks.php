<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpapy\Auth\Context;
use Kpapy\Resource;
use Modules\Core\Permissions\Authorization;
use Modules\Tasks\Calendar\DueDates;
use Modules\Tasks\Controllers\Tasks as TasksCrud;
use Modules\Tasks\Services\Assignees;
use Modules\Tasks\Services\TaskRepository;
use Modules\Tasks\Storage\AttachmentUploads;

/**
 * API resource `api/v1/tasks/tasks` (tutorial 11, kpapy): the to-do tasks with **the same
 * permissions as the CRUD** (`setResource('tasks-tasks')`: `list`, `show`, `add`, `edit`,
 * `delete`, `trash`, `restore`, `purge`, `complete`), narrowed by the token's scopes. Ids are the
 * masked codes of the back office (`app.maskKey`, context `tasks`).
 *
 * - filters `status`, `priority`, `visibility`, `project_id`, `labels`, `assignees`
 *   (`?filter[status]=todo`); search `q` in the title and description;
 * - includes `?include=project,assignees`;
 * - `estimate_hours` only with `tasks-tasks.estimates` (hidden otherwise, like the CRUD);
 * - `files`: the attachments with their download URLs (read-only; attach files in the back office);
 * - custom operation `POST api/v1/tasks/tasks/{id}/complete` (`tasks-tasks.complete`);
 * - every write keeps the calendar and the attachments index in step and notifies new assignees.
 */
final class Tasks extends Controller
{
    /** @var array<int, list<int>> */
    private array $before = [];

    public function index(): ResponseInterface
    {
        $user  = self::user();
        $actor = $user !== null ? (int) $user->id : null;
        $api   = (new Resource())
            ->setTable('tasks')
            ->setResource('tasks-tasks')
            ->softDeletes()
            ->setRelation('project_id', 'task_projects', ['slug', 'color'], ['as' => 'project'])
            ->setRelationNN('assignees', 'task_assignees', 'users', 'task_id', 'user_id', ['username'], ['where' => ['active' => 1, 'deleted_at' => null]])
            ->fieldType('status', 'select', ['options' => TasksCrud::options('statuses', TaskRepository::STATUSES)])
            ->fieldType('priority', 'select', ['options' => TasksCrud::options('priorities', TaskRepository::PRIORITIES)])
            ->fieldType('visibility', 'select', ['options' => TasksCrud::options('visibilities', TaskRepository::VISIBILITIES)])
            ->fieldType('labels', 'multiselect', ['options' => TasksCrud::options('labels', TaskRepository::LABELS)])
            ->fieldType('due_on', 'date')
            ->fieldType('estimate_hours', 'decimal', ['scale' => 2])
            ->fieldType('started_at', 'datetime')
            ->fieldType('completed_at', 'datetime')
            ->fieldType('files', 'virtual')
            ->callbackField('files', static fn ($value, array $row): array => AttachmentUploads::files(is_string($row['attachments'] ?? null) ? $row['attachments'] : null))
            ->fields(['id', 'title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'files', 'started_at', 'completed_at', 'created_at', 'updated_at'])
            ->listFields(['id', 'title', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'visibility', 'completed_at'])
            ->writeFields(['title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility'])
            ->requiredFields(['title'])
            ->setRules([
                'title'          => 'max_length[190]',
                'description'    => 'permit_empty|max_length[5000]',
                'estimate_hours' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than[10000]',
            ])
            ->setDefault('status', 'todo')
            ->setDefault('priority', 'normal')
            ->setDefault('visibility', 'private')
            ->filters(['status', 'priority', 'visibility', 'project_id', 'labels', 'assignees'])
            ->searchFields(['title', 'description'])
            ->defaultOrder('id', 'desc')
            ->beforeInsert(static fn (array $data): array => self::completion($data, null) + ['created_by' => $actor])
            ->beforeUpdate(function (array $data, string $id, array $old): array {
                $this->before[(int) $id] = (new TaskRepository())->assigneeIds((int) $id);

                return self::completion($data, $old);
            })
            ->afterWrite(function (string $operation, string $id) use ($actor): void {
                DueDates::sync((int) $id);

                if ($operation === 'insert' || $operation === 'update') {
                    Assignees::notifyNew((int) $id, $this->before[(int) $id] ?? [], $actor);
                }
            })
            ->addOperation('complete', static function (?string $id, ?array $row, Resource $api): array {
                if (! is_array($row) || ($row['status'] ?? '') === 'done') {
                    $api->fail(lang('Tasks.api.alreadyDone'));
                }

                (new TaskRepository())->setStatus((int) $row['id'], 'done');

                return ['status' => 'done'];
            }, ['description' => lang('Tasks.api.complete')])
            ->describe(lang('Tasks.api.tasksSummary'), lang('Tasks.api.tasksDescription'));

        if ($user === null || ! Authorization::for($user)->can('tasks-tasks.estimates')) {
            $api->hideFields(['estimate_hours']);
        }

        return $api->render();
    }

    /**
     * `started_at` and `completed_at` follow the status, like the CRUD.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $old
     *
     * @return array<string, mixed>
     */
    private static function completion(array $data, ?array $old): array
    {
        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $data = TaskRepository::statusDates($data['status'], $old ?? []) + $data;
        }

        return $data;
    }

    /**
     * The token's user as a Shield entity (null without one: the `kpapy` filter refuses it first).
     */
    private static function user(): ?User
    {
        $user = Context::user();

        if ($user === null || $user instanceof User) {
            return $user;
        }

        $id = $user->id ?? null;

        return is_numeric($id) ? model(UserModel::class)->findById((int) $id) : null;
    }
}
