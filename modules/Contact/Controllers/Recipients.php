<?php

declare(strict_types=1);

namespace Modules\Contact\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Contact\Services\Recipients as RecipientList;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;

/**
 * Back office of the recipients, `backend/contact/recipients` (resource `contact-recipients`):
 * the name per language (shown on the form), the addresses that receive each message (up to
 * five, never shown on the site), the group whose members are notified and read the messages,
 * the order and whether the form offers it.
 */
final class Recipients extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud   = $this->crud('contact-recipients');
        $groups = [];

        foreach ((new GroupRegistry())->all() as $name => $group) {
            $groups[$name] = $group['title'] !== '' ? $group['title'] : $name;
        }

        $crud->setTable('contact_recipients')
            ->setSubject(lang('Contact.recipient'), lang('Contact.recipients'))
            ->unsetExport()
            ->setTranslations('contact_recipient_translations', 'recipient_id', 'locale', ['name'], ContentTranslations::locales(), ContentTranslations::crudOptions([
                'types' => ['name' => 'text'],
                'rules' => ['name' => 'required|max_length[120]'],
            ]))
            ->fieldType('emails', 'textarea')
            ->fieldType('group_name', 'select', ['options' => $groups])
            ->fieldType('active', 'boolean')
            ->fieldType('position', 'number')
            ->columns(['name', 'group_name', 'position', 'active'])
            ->fields(['name', 'emails', 'group_name', 'position', 'active'])
            ->showFields(['name', 'emails', 'group_name', 'position', 'active', 'created_at', 'updated_at'])
            ->searchFields(['name', 'emails'])
            ->filters(['active', 'group_name'])
            ->setRules([
                'group_name' => 'permit_empty|in_list[' . implode(',', array_keys($groups)) . ']',
                'position'   => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
                'emails'     => 'permit_empty|max_length[1000]',
            ])
            ->setDefault('active', 1)
            ->setDefault('position', 0)
            ->setHelp('emails', lang('Contact.help.emails', [RecipientList::MAX_EMAILS]))
            ->setHelp('group_name', lang('Contact.help.group'))
            ->displayAs([
                'name'       => lang('Contact.fields.recipientName'),
                'emails'     => lang('Contact.fields.emails'),
                'group_name' => lang('Contact.fields.group'),
                'position'   => lang('Contact.fields.position'),
                'active'     => lang('Contact.fields.active'),
                'created_at' => lang('Contact.fields.created_at'),
                'updated_at' => lang('Contact.fields.updated_at'),
            ])
            ->beforeInsert(static fn (array $data): array|string => self::clean($data))
            ->beforeUpdate(static fn (array $data): array|string => self::clean($data))
            ->defaultOrder('position', 'asc');

        return $this->renderCrud($crud);
    }

    /**
     * Normalises the addresses (one per line) and refuses invalid ones.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|string
     */
    private static function clean(array $data): array|string
    {
        if (array_key_exists('emails', $data)) {
            $value = (string) ($data['emails'] ?? '');
            $error = RecipientList::emailsError($value);

            if ($error !== null) {
                return $error;
            }

            $emails         = RecipientList::parseEmails($value);
            $data['emails'] = $emails === [] ? null : implode("\n", $emails);
        }

        if (array_key_exists('group_name', $data) && ($data['group_name'] ?? '') === '') {
            $data['group_name'] = null;
        }

        return $data;
    }
}
