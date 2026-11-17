<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;
use Modules\Tasks\Services\TaskRequests;

/**
 * Back office of the requests sent from the public form (tutorial 12), `backend/tasks/requests`
 * (resource `tasks-requests`): read-only list and details, delete, export, and the operations
 * `accept` (creates a private to-do task from the request and opens it) and `reject`, each with
 * its permission declared in the manifest. Requests are never edited.
 */
final class Requests extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('tasks-requests');

        $crud->setTable('task_requests')
            ->setSubject(lang('Tasks.request'), lang('Tasks.requests'))
            ->unsetAdd()
            ->unsetEdit()
            ->fieldType('status', 'select', ['options' => Tasks::options('requestStatuses', TaskRequests::STATUSES)])
            ->fieldType('details', 'textarea')
            ->setRelation('task_id', 'tasks', 'title')
            ->callbackColumn('status', static fn ($value): string => self::statusBadge((string) $value))
            ->callbackExport('status', static fn ($value): string => (string) $value)
            ->columns(['title', 'name', 'email', 'status', 'created_at'])
            ->showFields(['title', 'details', 'name', 'email', 'locale', 'status', 'task_id', 'created_at', 'updated_at'])
            ->exportColumns(['title', 'details', 'name', 'email', 'locale', 'status', 'created_at'])
            ->searchFields(['title', 'name', 'email'])
            ->filters(['status'])
            ->displayAs([
                'title'      => lang('Tasks.fields.title'),
                'details'    => lang('Tasks.fields.details'),
                'name'       => lang('Tasks.fields.requesterName'),
                'email'      => lang('Tasks.fields.email'),
                'locale'     => lang('Tasks.fields.locale'),
                'status'     => lang('Tasks.fields.status'),
                'task_id'    => lang('Tasks.fields.task'),
                'created_at' => lang('Tasks.fields.created_at'),
                'updated_at' => lang('Tasks.fields.updated_at'),
            ])
            ->defaultOrder('id', 'desc');

        $crud->addOperation('accept', static function (string $id, array $row, Crud $crud): ResponseInterface|string {
            $taskId = (new TaskRequests())->accept($row, (int) auth()->id());

            if ($taskId === null) {
                return $crud->backToList('danger', lang('Tasks.requestNotNew'));
            }

            session()->setFlashdata('success', lang('Tasks.requestAccepted'));

            return redirect()->to(site_url('backend/tasks/tasks/edit/' . Crud::maskId($taskId, 'tasks')));
        }, [
            'label'      => lang('Tasks.accept'),
            'icon'       => 'bi bi-check2',
            'class'      => 'btn-outline-success',
            'confirm'    => lang('Tasks.acceptConfirm'),
            'permission' => 'accept',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') === 'new',
        ]);

        $crud->addOperation('reject', static function (string $id, array $row, Crud $crud): ResponseInterface|string {
            return (new TaskRequests())->reject($row) ? lang('Tasks.requestRejected') : $crud->backToList('danger', lang('Tasks.requestNotNew'));
        }, [
            'label'      => lang('Tasks.reject'),
            'icon'       => 'bi bi-x-lg',
            'confirm'    => lang('Tasks.rejectConfirm'),
            'permission' => 'reject',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') === 'new',
        ]);

        return $this->renderCrud($crud);
    }

    private static function statusBadge(string $status): string
    {
        $colour = ['new' => 'warning', 'accepted' => 'success', 'rejected' => 'secondary'][$status] ?? 'secondary';

        return in_array($status, TaskRequests::STATUSES, true) ? ui_badge(lang('Tasks.requestStatuses.' . $status), $colour) : esc($status);
    }
}
