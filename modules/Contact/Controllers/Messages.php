<?php

declare(strict_types=1);

namespace Modules\Contact\Controllers;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Contact\Services\ContactMessages;
use Modules\Contact\Services\MessageAccess;
use Modules\Core\Controllers\BackendController;

/**
 * Back office of the contact messages, `backend/contact/messages` (resource `contact-messages`):
 * list, details and delete, and the operations "Mark answered" (`answer`) and "Archive"
 * (`archive`), each with its permission. Messages are never edited. Record rule: without
 * `contact-messages.all` only the messages addressed to the recipients of the user's groups
 * exist for this CRUD (`MessageAccess`, enforced on every action, forged ids included).
 * Every text typed by the visitor is escaped (kpcrudy escapes values). "Reply" opens the
 * user's own mail program addressed to the visitor (`mailto:`).
 */
final class Messages extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('contact-messages');
        $user = $this->currentUserOrNobody();

        $crud->setTable('contact_messages')
            ->setSubject(lang('Contact.message'), lang('Contact.messages'))
            ->unsetAdd()
            ->unsetEdit()
            ->unsetExport()
            ->where(static fn (BaseBuilder $builder) => MessageAccess::apply($builder, $user))
            ->fieldType('message', 'textarea')
            ->callbackColumn('status', static fn ($value): string => self::statusBadge((string) $value))
            ->columns(['subject', 'name', 'recipient_name', 'status', 'created_at'])
            ->showFields(['subject', 'message', 'name', 'email', 'recipient_name', 'locale', 'status', 'answered_at', 'created_at'])
            ->searchFields(['subject', 'name', 'email', 'message'])
            ->filters(['status', 'recipient_name'])
            ->displayAs([
                'subject'        => lang('Contact.fields.subject'),
                'message'        => lang('Contact.fields.message'),
                'name'           => lang('Contact.fields.name'),
                'email'          => lang('Contact.fields.email'),
                'recipient_name' => lang('Contact.fields.recipient'),
                'locale'         => lang('Contact.fields.locale'),
                'status'         => lang('Contact.fields.status'),
                'answered_at'    => lang('Contact.fields.answered_at'),
                'created_at'     => lang('Contact.fields.created_at'),
            ])
            ->defaultOrder('id', 'desc');

        $crud->addAction(lang('Contact.reply'), static fn (array $row): string => 'mailto:' . rawurlencode((string) $row['email'])
            . '?subject=' . rawurlencode('Re: ' . str_replace(["\r", "\n"], ' ', (string) $row['subject'])), [
                'icon'       => 'bi bi-reply',
                'permission' => 'show',
            ]);

        $crud->addOperation('answer', static function (string $id, array $row, Crud $crud): ResponseInterface|string {
            return (new ContactMessages())->mark((int) $id, 'answered', (int) auth()->id()) ? lang('Contact.markedAnswered') : $crud->backToList('warning', lang('Contact.alreadyAnswered'));
        }, [
            'label'      => lang('Contact.answer'),
            'icon'       => 'bi bi-check2',
            'class'      => 'btn-outline-success',
            'permission' => 'answer',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') === 'new',
        ]);

        $crud->addOperation('archive', static function (string $id, array $row, Crud $crud): ResponseInterface|string {
            return (new ContactMessages())->mark((int) $id, 'archived') ? lang('Contact.archived') : $crud->backToList('warning', lang('Contact.alreadyArchived'));
        }, [
            'label'      => lang('Contact.archive'),
            'icon'       => 'bi bi-archive',
            'permission' => 'archive',
            'visible'    => static fn (?array $row): bool => $row === null || ($row['status'] ?? '') !== 'archived',
        ]);

        return $this->renderCrud($crud);
    }

    public static function statusBadge(string $status): string
    {
        $colour = ['new' => 'warning', 'answered' => 'success', 'archived' => 'secondary'][$status] ?? 'secondary';

        return in_array($status, ContactMessages::STATUSES, true) ? ui_badge(lang('Contact.statuses.' . $status), $colour) : esc($status);
    }
}
