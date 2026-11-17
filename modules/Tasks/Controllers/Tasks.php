<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Crud\AuditedCrud;
use Modules\Tasks\Calendar\DueDates;
use Modules\Tasks\Services\Assignees;
use Modules\Tasks\Services\TaskRepository;
use Modules\Tasks\Storage\AttachmentUploads;

/**
 * Back office of the to-do tasks, `backend/tasks/tasks` (resource `tasks-tasks`).
 *
 * Tutorial 1 (a complete CRUD): the project (1-N, a select of translated names), the assignees
 * (N-N, `setRelationNN()` over `task_assignees`), filters, and the custom operation `complete`
 * with its own permission `tasks-tasks.complete`.
 *
 * Tutorial 2 (extending the CRUD): labels as a kpcrudy `multiselect`, the estimate hidden without
 * `tasks-tasks.estimates` (`hideFields()`, also in the export and against forged posts), an
 * `afterWrite()` hook (calendar, attachments, notifications to new assignees) and the bulk
 * action `complete` declared in the manifest. The translated field is the project's name
 * (`Projects`).
 *
 * Tutorial 10: the attachments field is the Core's file picker on the private disk
 * (`AttachmentUploads`); a posted key is accepted only when kept or uploaded/chosen through the
 * field. Writes and operations are logged by the Core (`AuditedCrud`).
 */
final class Tasks extends BackendController
{
    /** @var array<int, list<int>> assignees of each record before an update (for the notifications) */
    private array $assigneesBefore = [];

    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('tasks-tasks');
        $me   = (int) auth()->id();

        $crud->setTable('tasks')
            ->setSubject(lang('Tasks.task'), lang('Tasks.tasks'))
            ->setRelationNN('assignees', 'task_assignees', 'users', 'task_id', 'user_id', 'username', ['where' => ['active' => 1, 'deleted_at' => null], 'order' => 'username'])
            ->setRelation('created_by', 'users', 'username')
            ->fieldType('project_id', 'select', ['options' => (new TaskRepository())->projectNames()])
            ->fieldType('status', 'select', ['options' => self::options('statuses', TaskRepository::STATUSES)])
            ->fieldType('priority', 'select', ['options' => self::options('priorities', TaskRepository::PRIORITIES)])
            ->fieldType('visibility', 'select', ['options' => self::options('visibilities', TaskRepository::VISIBILITIES)])
            ->fieldType('labels', 'multiselect', ['options' => self::options('labels', TaskRepository::LABELS)])
            ->fieldType('description', 'textarea', ['attrs' => ['rows' => 5]])
            ->fieldType('due_on', 'date')
            ->fieldType('estimate_hours', 'decimal', ['attrs' => ['min' => 0, 'step' => '0.25']])
            ->fieldType('completed_at', 'readonly')
            ->callbackField('attachments', static fn ($value, ?array $row, array $field): string => AttachmentUploads::renderPicker('attachments', $value, $field))
            ->callbackColumn('attachments', static fn ($value): string => self::attachmentLinks(is_string($value) ? $value : null))
            ->callbackColumn('status', static fn ($value): string => self::statusBadge((string) $value))
            ->callbackColumn('due_on', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'date'))
            ->callbackExport('status', static fn ($value): string => (string) $value)
            ->callbackExport('due_on', static fn ($value): string => (string) $value)
            ->columns(['title', 'project_id', 'status', 'priority', 'due_on', 'assignees', 'labels'])
            ->fields(['title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'attachments'])
            ->showFields(['title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'attachments', 'created_by', 'completed_at', 'created_at', 'updated_at'])
            ->exportColumns(['title', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'completed_at'])
            ->searchFields(['title', 'description'])
            ->filters(['project_id', 'status', 'priority', 'labels', 'assignees', 'visibility'])
            ->setRules([
                'title'          => 'required|max_length[190]',
                'description'    => 'permit_empty|max_length[5000]',
                'due_on'         => 'permit_empty|valid_date[Y-m-d]',
                'estimate_hours' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than[10000]',
                'attachments'    => 'permit_empty|max_length[200]',
            ])
            ->setDefault('status', 'todo')
            ->setDefault('priority', 'normal')
            ->setDefault('visibility', 'private')
            ->setHelp('visibility', lang('Tasks.help.visibility'))
            ->setHelp('attachments', lang('Tasks.help.attachments'))
            ->setHelp('estimate_hours', lang('Tasks.help.estimate'))
            ->displayAs(self::labels())
            ->beforeInsert(fn (array $data): array|string => $this->beforeSave($data, null, [], $me))
            ->beforeUpdate(fn (array $data, string $id, array $old): array|string => $this->beforeSave($data, (int) $id, $old, $me))
            // Tutorial 2: one hook after every write (form, bulk actions, trash): the calendar,
            // the attachments index and the notification to the users just assigned.
            ->afterWrite(function (string $operation, string $id) use ($me): void {
                $taskId = (int) $id;
                DueDates::sync($taskId);

                if ($operation === 'insert' || $operation === 'update') {
                    AttachmentUploads::sync($taskId);
                    Assignees::notifyNew($taskId, $this->assigneesBefore[$taskId] ?? [], $me);
                }
            })
            ->defaultOrder('id', 'desc');

        // Tutorial 2: the estimate exists only for users with `tasks-tasks.estimates`.
        if (! $this->can('tasks-tasks.estimates')) {
            $crud->hideFields(['estimate_hours']);
        }

        // Tutorial 1: a custom operation with its own permission (`tasks-tasks.complete`).
        $crud->addOperation('complete', static function (string $id): string {
            (new TaskRepository())->setStatus((int) $id, 'done');

            return lang('Tasks.completedOk');
        }, [
            'label'      => lang('Tasks.complete'),
            'icon'       => 'bi bi-check2-circle',
            'class'      => 'btn-outline-success',
            'confirm'    => lang('Tasks.completeConfirm'),
            'permission' => 'complete',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') !== 'done',
        ]);

        // Tutorial 2: a bulk action with its permission declared in the manifest.
        $crud->addBulkAction('complete', lang('Tasks.complete'), static function (array $ids): string {
            $repository = new TaskRepository();
            $rows       = db_connect()->table('tasks')->select('id')->whereIn('id', array_map('intval', $ids))->where('status !=', 'done')->get()->getResultArray();

            foreach ($rows as $row) {
                $repository->setStatus((int) $row['id'], 'done');
            }

            return lang('Tasks.bulkCompleted', [count($rows)]);
        }, ['permission' => 'complete', 'confirm' => lang('Tasks.completeConfirm')]);

        return $this->renderCrud($crud);
    }

    /**
     * The creator of new to-do tasks, `completed_at` following the status, the attachments
     * checked, and the assignees before an update remembered for the notification.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $old
     *
     * @return array<string, mixed>|string
     */
    private function beforeSave(array $data, ?int $id, array $old, int $me): array|string
    {
        if (array_key_exists('attachments', $data)) {
            $current             = is_string($old['attachments'] ?? null) ? $old['attachments'] : null;
            $data['attachments'] = is_string($data['attachments']) && trim($data['attachments']) !== '' ? trim($data['attachments']) : null;

            if (! AttachmentUploads::accepts('attachments', $data['attachments'], $current)) {
                return lang('Core.picker.errors.value');
            }
        }

        if (array_key_exists('status', $data)) {
            $wasDone = ($old['status'] ?? '') === 'done';
            $isDone  = $data['status'] === 'done';

            if ($isDone && ! $wasDone) {
                $data['completed_at'] = Time::now()->toDateTimeString();
            } elseif (! $isDone) {
                $data['completed_at'] = null;
            }
        }

        if ($id === null) {
            $data['created_by'] = $me;
        } else {
            $this->assigneesBefore[$id] = (new TaskRepository())->assigneeIds($id);
        }

        return $data;
    }

    /**
     * Translated options of a select: value => label.
     *
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    public static function options(string $group, array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = lang('Tasks.' . $group . '.' . $value);
        }

        return $options;
    }

    public static function statusBadge(string $status): string
    {
        $colour = ['todo' => 'secondary', 'doing' => 'primary', 'done' => 'success'][$status] ?? 'secondary';

        return in_array($status, TaskRepository::STATUSES, true) ? ui_badge(lang('Tasks.statuses.' . $status), $colour) : esc($status);
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        $labels = [];

        foreach (['title', 'description', 'project_id', 'status', 'priority', 'labels', 'due_on', 'assignees', 'estimate_hours', 'visibility', 'attachments', 'created_by', 'completed_at', 'created_at', 'updated_at'] as $field) {
            $labels[$field] = lang('Tasks.fields.' . $field);
        }

        return $labels;
    }

    private static function attachmentLinks(?string $value): string
    {
        $files = AttachmentUploads::files($value);

        if ($files === []) {
            return '';
        }

        $items = array_map(static fn (array $file): string => '<li><a href="' . esc($file['url'], 'attr') . '"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . esc($file['name']) . '</a></li>', $files);

        return '<ul class="list-unstyled mb-0 small">' . implode('', $items) . '</ul>';
    }
}
