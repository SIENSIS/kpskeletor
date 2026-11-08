<?php

declare(strict_types=1);

namespace Modules\Users\Controllers;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Auth\Services\Impersonation;
use Modules\Auth\Services\TwoFactor;
use Modules\Core\Controllers\BackendController;
use Modules\Users\Services\UserAccounts;

/**
 * Users (resource `users-users`, kpcrudy over Shield's `users` table; docs/users.md).
 *
 * - List and details with the email (Shield's identity), groups, 2FA method and status; search by
 *   user name.
 * - Add: user name, email and an initial password (the user must change it at the first login,
 *   and joins the default group). Edit: user name, email and "active". Both go through Shield
 *   for the identity; no deletion (D-15-03).
 * - Row operations: ban/unban, force a password reset, reset 2FA (re-authentication), log out
 *   everywhere, impersonate (superadmins only, re-authentication) and groups and permissions
 *   (`access`, its own screen).
 * - Every operation on an account is limited by `EscalationGuard::canManageUser()` (nobody acts on
 *   a stronger account) and, except edit, refused on one's own account.
 */
final class Users extends BackendController
{
    private const USERNAME_RULES = ['required', 'min_length[3]', 'max_length[30]', 'regex_match[/\A[a-zA-Z0-9\.]+\z/]', 'is_unique[users.username,id,{id}]'];

    public function index(): ResponseInterface|string
    {
        $actor    = $this->actor();
        $accounts = new UserAccounts();
        $targets  = [];
        $target   = static function (array $row) use ($accounts, &$targets): ?User {
            $id = (int) ($row['id'] ?? 0);

            return $targets[$id] ??= $accounts->find($id);
        };
        // Accounts the actor may manage: oneself included (edit) or not (operations).
        $manage = static fn (?array $row): bool => $row === null || (($user = $target($row)) !== null && $accounts->canManage($actor, $user, true));
        $other  = static fn (?array $row): bool => $row === null || (($user = $target($row)) !== null && $accounts->canManage($actor, $user));

        $email = static fn (array $row): string => UserAccounts::email((int) ($row['id'] ?? 0));

        $crud = $this->crud('users-users')
            ->setTable('users')
            ->softDeletes(false)
            ->where(['deleted_at' => null])
            ->setSubject(lang('Users.users.user'), lang('Users.users.title'))
            ->columns(['username', 'email', 'groups', 'two_factor', 'status', 'last_active'])
            ->addFields(['username', 'email', 'password', 'password_confirm'])
            ->editFields(['username', 'email', 'active'])
            ->showFields(['username', 'email', 'groups', 'permissions', 'two_factor', 'status', 'active', 'last_active', 'created_at'])
            ->searchFields(['username'])
            ->filters(['active'])
            ->unsortable(['email', 'groups', 'two_factor', 'status'])
            ->displayAs([
                'username'         => lang('Users.users.username'),
                'email'            => lang('Users.users.email'),
                'password'         => lang('Users.users.password'),
                'password_confirm' => lang('Users.users.passwordConfirm'),
                'groups'           => lang('Users.users.groups'),
                'permissions'      => lang('Users.users.permissions'),
                'two_factor'       => lang('Users.users.twoFactor'),
                'status'           => lang('Users.users.status'),
                'active'           => lang('Users.users.active'),
                'last_active'      => lang('Users.users.lastActive'),
                'created_at'       => lang('Users.users.created'),
            ])
            ->fieldType('active', 'boolean')
            ->setRules('username', self::USERNAME_RULES)
            ->requiredFields(['email', 'password', 'password_confirm'])
            ->setHelp('password', lang('Users.users.passwordHelp'))
            ->callbackColumn('email', static fn ($value, array $row): string => esc($email($row)))
            ->callbackColumn('groups', static fn ($value, array $row): string => self::groupBadges($target($row)))
            ->callbackColumn('permissions', static fn ($value, array $row): string => esc(implode(', ', $target($row)?->getPermissions() ?? [])))
            ->callbackColumn('two_factor', static fn ($value, array $row): string => self::twoFactorBadge((int) ($row['id'] ?? 0)))
            ->callbackColumn('status', static fn ($value, array $row): string => self::statusBadge($row))
            ->callbackColumn('last_active', static fn ($value): string => $value === null || $value === '' ? '—' : ui_date((string) $value, 'datetime'))
            ->callbackField('email', static fn ($value, ?array $row, array $field): string => self::input('email', 'email', $row === null ? (string) old('email') : UserAccounts::email((int) $row['id']), $field, 'email'))
            ->callbackField('password', static fn ($value, ?array $row, array $field): string => self::input('password', 'password', '', $field, 'new-password'))
            ->callbackField('password_confirm', static fn ($value, ?array $row, array $field): string => self::input('password_confirm', 'password', '', $field, 'new-password'))
            ->setPermission('edit', $manage)
            ->defaultOrder('username', 'asc')
            ->unsetDelete()->unsetExport()
            ->beforeInsert(fn (array $data): array|string => $this->checkNewAccount($data))
            ->afterInsert(function (string $id) use ($accounts, $actor): void {
                $accounts->completeNewAccount((int) $id, (string) $this->posted('email'), (string) $this->posted('password'), $actor);
            })
            ->beforeUpdate(fn (array $data, string $id, array $old): array|string => $this->checkUpdate($data, (int) $id, $actor))
            ->afterUpdate(function (string $id) use ($accounts, $actor): void {
                $user = $accounts->find((int) $id);

                if ($user !== null) {
                    $accounts->changeEmail($user, (string) $this->posted('email'), $actor);
                }
            });

        $crud->addOperation('ban', static function (string $id, array $row, Crud $crud) use ($accounts, $actor, $target): string|ResponseInterface {
            $user = $target($row);

            if ($user === null) {
                return $crud->backToList('danger', lang('Users.users.notFound'));
            }

            return $accounts->toggleBan($user, $actor) ? lang('Users.users.banned', [$user->username]) : lang('Users.users.unbanned', [$user->username]);
        }, ['label' => lang('Users.ops.ban'), 'icon' => 'bi bi-slash-circle', 'class' => 'btn-outline-danger', 'confirm' => lang('Users.ops.banConfirm'), 'visible' => $other]);

        $crud->addOperation('forcereset', static function (string $id, array $row) use ($accounts, $actor, $target): string {
            $user = $target($row);
            $user === null || $accounts->forceReset($user, $actor);

            return lang('Users.users.resetForced');
        }, ['label' => lang('Users.ops.forcereset'), 'icon' => 'bi bi-key', 'confirm' => lang('Users.ops.forceresetConfirm'), 'visible' => $other]);

        $crud->addOperation('reset2fa', function (string $id, array $row) use ($accounts, $actor, $target): string|ResponseInterface {
            if (($response = $this->requireReauth()) !== null) {
                return $response;
            }

            $user = $target($row);
            $user === null || $accounts->resetTwoFactor($user, $actor);

            return lang('Users.users.twoFactorReset');
        }, ['label' => lang('Users.ops.reset2fa'), 'icon' => 'bi bi-shield-x', 'confirm' => lang('Users.ops.reset2faConfirm'),
            'visible' => static fn (?array $row): bool => $other($row) && ($row === null || (new TwoFactor())->enabled((int) $row['id']))]);

        $crud->addOperation('logout', static function (string $id, array $row) use ($accounts, $actor, $target): string {
            $user = $target($row);

            return lang('Users.users.loggedOut', [$user === null ? 0 : $accounts->logoutEverywhere($user, $actor)]);
        }, ['label' => lang('Users.ops.logout'), 'icon' => 'bi bi-box-arrow-right', 'confirm' => lang('Users.ops.logoutConfirm'), 'visible' => $other]);

        $crud->addOperation('impersonate', function (string $id, array $row, Crud $crud) use ($actor, $target): ResponseInterface {
            if (($response = $this->requireReauth()) !== null) {
                return $response;
            }

            $user = $target($row);

            if ($user === null || ! (new Impersonation())->start($actor, $user, (int) setting('Users.impersonationMinutes'))) {
                return $crud->backToList('danger', lang('Users.users.cannotImpersonate'));
            }

            return redirect()->to(site_url('backend/profile'))->with('warning', lang('Auth.impersonation.banner', [$user->username]));
        }, ['label' => lang('Users.ops.impersonate'), 'icon' => 'bi bi-incognito', 'confirm' => lang('Users.ops.impersonateConfirm'),
            'visible' => static fn (?array $row): bool => $row === null || (($user = $target($row)) !== null && (new Impersonation())->allowed($actor, $user))]);

        $crud->addOperation(
            'access',
            static fn (string $id): ResponseInterface => redirect()->to(site_url('backend/users/access/' . (int) $id)),
            ['label' => lang('Users.ops.access'), 'icon' => 'bi bi-person-lock', 'method' => 'get', 'visible' => $other]
        );

        return $this->renderCrud($crud);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|string
     */
    private function checkNewAccount(array $data): array|string
    {
        $values = [
            'email'            => trim((string) $this->posted('email')),
            'password'         => (string) $this->posted('password'),
            'password_confirm' => (string) $this->posted('password_confirm'),
            'username'         => (string) ($data['username'] ?? ''),
        ];

        $validation = service('validation')->reset()->setRules([
            'email'            => ['label' => 'Users.users.email', 'rules' => 'required|max_length[254]|valid_email'],
            'password'         => ['label' => 'Users.users.password', 'rules' => ['required', Passwords::getMaxLengthRule(), 'strong_password[]']],
            'password_confirm' => ['label' => 'Users.users.passwordConfirm', 'rules' => 'required|matches[password]'],
        ]);

        if (! $validation->run($values)) {
            return implode(' ', $validation->getErrors());
        }

        if (UserAccounts::emailTaken($values['email'])) {
            return lang('Users.users.emailTaken');
        }

        $data['active'] = 1;

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|string
     */
    private function checkUpdate(array $data, int $id, User $actor): array|string
    {
        $email = trim((string) $this->posted('email'));

        if ($email !== '' && (! (bool) filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254)) {
            return lang('Users.users.emailInvalid');
        }

        if ($email !== '' && UserAccounts::emailTaken($email, $id)) {
            return lang('Users.users.emailTaken');
        }

        if ($id === (int) $actor->id && array_key_exists('active', $data) && ! (bool) $data['active']) {
            return lang('Users.users.cannotDeactivateSelf');
        }

        return $data;
    }

    private function posted(string $field): mixed
    {
        return $this->request instanceof IncomingRequest ? $this->request->getPost($field) : null;
    }

    /**
     * The logged-in user. Without one (only `permissions:check` inspects the CRUD that way), a
     * stand-in that holds nothing, so every record rule denies.
     */
    private function actor(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : new User(['id' => 0, 'username' => '']);
    }

    /**
     * The input of a field that is not a column of `users` (saved through Shield by the hooks);
     * kpcrudy prints its label, help and wrapper.
     *
     * @param array<string, mixed> $field
     */
    private static function input(string $name, string $type, string $value, array $field, string $autocomplete): string
    {
        $invalid = ($field['error'] ?? '') !== '' ? ' is-invalid' : '';

        return '<input type="' . esc($type, 'attr') . '" class="form-control' . $invalid . '" id="' . esc('kp-' . $name, 'attr') . '"'
            . ' name="' . esc($name, 'attr') . '" value="' . esc($value, 'attr') . '" autocomplete="' . esc($autocomplete, 'attr') . '" required maxlength="254">';
    }

    private static function groupBadges(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $titles = (array) setting('AuthGroups.groups');
        $html   = [];

        foreach ($user->getGroups() ?? [] as $group) {
            $html[] = ui_badge((string) ($titles[$group]['title'] ?? $group), $group === 'superadmin' ? 'danger' : 'secondary');
        }

        return implode(' ', $html);
    }

    private static function twoFactorBadge(int $userId): string
    {
        $method = (new TwoFactor())->method($userId);

        return $method === null ? ui_badge(lang('Users.users.twoFactorOff'), 'light') : ui_badge(lang('Users.users.twoFactor_' . $method), 'success', 'bi bi-shield-check');
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function statusBadge(array $row): string
    {
        if (($row['status'] ?? null) === 'banned') {
            return ui_badge(lang('Users.users.statusBanned'), 'danger');
        }

        return (bool) ($row['active'] ?? false) ? ui_badge(lang('Users.users.statusActive'), 'success') : ui_badge(lang('Users.users.statusInactive'), 'secondary');
    }
}
