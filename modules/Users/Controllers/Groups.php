<?php

declare(strict_types=1);

namespace Modules\Users\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\Authorization;
use Modules\Users\Services\BirthdayGreetings;

/**
 * Groups (resource `users-groups`, kpcrudy over the Core `groups` table; docs/users.md).
 *
 * - Name (lower case, set once), title, description and 2FA policy (`optional`/`required`).
 * - Every change (add, edit, delete) needs a re-authentication; the list does not.
 * - Changes are mirrored into Shield's groups (`GroupRegistry`); a new group gets the grants
 *   every reserved group holds (the personal screens).
 * - Reserved groups (`superadmin`, `admin`, `editor`, `user`) and groups with members cannot be
 *   deleted; only a superadmin edits the `superadmin` group.
 */
final class Groups extends BackendController
{
    public function index(): ResponseInterface|string
    {
        if ($this->changes() && ($response = $this->requireReauth()) !== null) {
            return $response;
        }

        $actor    = $this->currentUserOrNobody();
        $registry = new GroupRegistry();
        $policies = [
            GroupRegistry::OPTIONAL => lang('Users.groups.optional'),
            GroupRegistry::REQUIRED => lang('Users.groups.required'),
        ];

        $crud = $this->crud('users-groups')
            ->setTable(GroupRegistry::TABLE)
            ->setSubject(lang('Users.groups.group'), lang('Users.groups.title'))
            ->columns(['title', 'name', 'two_factor', 'reserved', 'members'])
            // Whose shared birthdays the members of this group see (D-25-08).
            ->setRelationNN('birthday_groups', 'group_birthday_audiences', GroupRegistry::TABLE, 'group_id', 'target_group_id', 'title', ['order' => 'title'])
            ->addFields(['name', 'title', 'description', 'two_factor', 'birthday_groups', 'birthday_greet'])
            ->editFields(['title', 'description', 'two_factor', 'birthday_groups', 'birthday_greet'])
            ->showFields(['name', 'title', 'description', 'two_factor', 'birthday_groups', 'birthday_greet', 'reserved', 'members', 'created_at'])
            // Birthday greetings (D-25-09): a switch stored in `group_birthday_greetings`.
            ->callbackField('birthday_greet', static fn ($value, ?array $row): string => self::greetSwitch($row))
            ->callbackColumn('birthday_greet', static fn ($value, array $row): string => BirthdayGreetings::greets((int) ($row['id'] ?? 0))
                ? ui_badge(lang('Users.greetings.on'), 'success', 'bi bi-balloon-heart') : ui_badge(lang('Users.greetings.off'), 'light'))
            ->searchFields(['name', 'title'])
            ->unsortable(['members'])
            ->displayAs([
                'name'        => lang('Users.groups.name'),
                'title'       => lang('Users.groups.titleField'),
                'description' => lang('Users.groups.description'),
                'two_factor'  => lang('Users.groups.twoFactor'),
                'reserved'    => lang('Users.groups.reserved'),
                'members'     => lang('Users.groups.members'),
                'birthday_groups' => lang('Users.groups.birthdayGroups'),
                'birthday_greet'  => lang('Users.groups.birthdayGreet'),
                'created_at'  => lang('Users.groups.created'),
            ])
            ->fieldType('two_factor', 'select', ['options' => $policies])
            ->fieldType('description', 'textarea', ['attrs' => ['rows' => 3]])
            ->setRules([
                'name'        => ['required', 'max_length[50]', 'regex_match[' . GroupRegistry::NAME_PATTERN . ']', 'is_unique[groups.name,id,{id}]'],
                'title'       => 'required|max_length[100]',
                'description' => 'permit_empty|max_length[255]',
                'two_factor'  => 'required|in_list[optional,required]',
            ])
            ->setHelp('name', lang('Users.groups.nameHelp'))
            ->setHelp('two_factor', lang('Users.groups.twoFactorHelp'))
            ->setHelp('birthday_groups', lang('Users.groups.birthdayGroupsHelp'))
            ->setDefault('two_factor', GroupRegistry::OPTIONAL)
            ->callbackColumn('two_factor', static fn ($value): string => $value === GroupRegistry::REQUIRED
                ? ui_badge(lang('Users.groups.required'), 'warning', 'bi bi-shield-lock') : ui_badge(lang('Users.groups.optional'), 'light'))
            ->callbackColumn('reserved', static fn ($value): string => (bool) $value ? ui_badge(lang('Users.groups.reservedYes'), 'secondary', 'bi bi-lock') : '')
            ->callbackColumn('members', static fn ($value, array $row): string => esc((string) $registry->memberCount((string) $row['name'])))
            ->defaultOrder('id', 'asc')
            ->unsetExport()
            ->setPermission('edit', static fn (?array $row): bool => $row === null || ($row['name'] ?? '') !== Authorization::SUPERADMIN || Authorization::isSuperadmin($actor))
            ->setPermission('delete', static fn (?array $row): bool => $row === null || $registry->canDelete((string) $row['name']))
            ->beforeInsert(static function (array $data) use ($registry): array|string {
                $error = $registry->nameError((string) ($data['name'] ?? ''));

                if ($error !== null) {
                    return $error;
                }

                $data['reserved'] = 0;

                return $data;
            })
            ->afterInsert(function (string $id, array $data) use ($registry): void {
                $registry->created((string) $data['name']);
                BirthdayGreetings::setGreets((int) $id, $this->request->getPost('birthday_greet') === '1');
            })
            ->afterUpdate(function (string $id) use ($registry): void {
                $registry->changed();
                BirthdayGreetings::setGreets((int) $id, $this->request->getPost('birthday_greet') === '1');
            })
            ->beforeDelete(static fn (string $id, array $row): ?string => $registry->canDelete((string) $row['name']) ? null : lang('Users.groups.cannotDelete'))
            ->afterDelete(static fn (string $id, array $row) => $registry->deleted((string) $row['name']));

        if ($this->can('users-matrix.view')) {
            $crud->addHeaderAction(lang('Users.matrix.title'), 'backend/users/permissions', ['icon' => 'bi bi-grid-3x3']);
        }

        return $this->renderCrud($crud);
    }

    /**
     * The switch "Greet the members on their birthday" of the form (a hidden 0 so that turning it
     * off is posted too).
     *
     * @param array<string, mixed>|null $row
     */
    private static function greetSwitch(?array $row): string
    {
        $checked = $row !== null && BirthdayGreetings::greets((int) ($row['id'] ?? 0));

        return '<input type="hidden" name="birthday_greet" value="0">'
            . '<div class="form-check form-switch">'
            . '<input class="form-check-input" type="checkbox" role="switch" id="birthday_greet" name="birthday_greet" value="1"' . ($checked ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="birthday_greet">' . esc(lang('Users.groups.birthdayGreetLabel')) . '</label>'
            . '</div><div class="form-text">' . esc(lang('Users.groups.birthdayGreetHelp')) . '</div>';
    }

    /**
     * Whether the request changes something (a POST, or the add and edit forms).
     */
    private function changes(): bool
    {
        return $this->request->getMethod() !== 'GET' || preg_match('#/(add|edit)(/|$)#', uri_string()) === 1;
    }
}
