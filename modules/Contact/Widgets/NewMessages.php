<?php

declare(strict_types=1);

namespace Modules\Contact\Widgets;

use CodeIgniter\Shield\Entities\User;
use Modules\Contact\Services\MessageAccess;
use Modules\Core\Widgets\Widget;

/**
 * Widget "New contact messages": the newest unanswered messages the user may read (the record
 * rule of `contact-messages`), with their count. Needs `contact-messages.list`.
 */
final class NewMessages extends Widget
{
    public function id(): string
    {
        return 'contact.new';
    }

    public function title(): string
    {
        return lang('Contact.widgets.new.title');
    }

    public function icon(): string
    {
        return 'bi bi-envelope';
    }

    public function permission(): string
    {
        return 'contact-messages.list';
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'Contact.widgets.limit',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $builder = db_connect()->table('contact_messages')->where('contact_messages.status', 'new');
        MessageAccess::apply($builder, $user);
        $total = $builder->countAllResults(false);
        $rows  = $builder->select('contact_messages.id, contact_messages.subject, contact_messages.name, contact_messages.recipient_name, contact_messages.created_at')
            ->orderBy('contact_messages.id', 'desc')->limit(max(1, min(20, (int) $options['limit'])))->get()->getResultArray();

        if ($rows === []) {
            return $this->empty(lang('Contact.widgets.new.empty'), 'bi bi-envelope-check');
        }

        return $this->view('Modules\Contact\Views\widgets\new', [
            'items' => $rows,
            'total' => $total,
            'url'   => backend_url('contact/messages'),
        ]);
    }
}
