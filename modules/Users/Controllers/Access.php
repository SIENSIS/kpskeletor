<?php

declare(strict_types=1);

namespace Modules\Users\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Exceptions\GroupRuleException;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Permissions\EscalationException;
use Modules\Users\Services\UserAccess;
use Modules\Users\Services\UserAccounts;

/**
 * A user's groups and individual permissions (`backend/users/access/<id>`, `users-users.access`
 * + `reauth` on the route). Checkboxes the actor may not change are disabled, and the server
 * applies the same rules (`UserAccess`): a forged POST changes nothing it should not.
 */
final class Access extends BackendController
{
    public function show(int|string $id): string
    {
        [$actor, $target] = $this->users($id);
        $access           = new UserAccess();

        $this->breadcrumb(lang('Users.users.title'), site_url('backend/users'))->breadcrumb((string) $target->username);

        return $this->render('Modules\Users\Views\access', [
            'title'       => lang('Users.access.title', [$target->username]),
            'icon'        => 'bi bi-person-lock',
            'target'      => $target,
            'email'       => UserAccounts::email((int) $target->id),
            'groups'      => $access->groups($actor, $target),
            'permissions' => $access->permissions($actor, $target),
            'formAction'  => site_url('backend/users/access/' . (int) $target->id),
        ]);
    }

    public function save(int|string $id): RedirectResponse
    {
        [$actor, $target] = $this->users($id);
        $back             = site_url('backend/users/access/' . (int) $target->id);
        $groups           = $this->request->getPost('groups');
        $permissions      = $this->request->getPost('permissions');

        try {
            $changed = (new UserAccess())->save(
                $actor,
                $target,
                array_values(array_filter(is_array($groups) ? $groups : [], 'is_string')),
                array_values(array_filter(is_array($permissions) ? $permissions : [], 'is_string')),
            );
        } catch (EscalationException|GroupRuleException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        return redirect()->to($back)->with($changed ? 'success' : 'info', lang($changed ? 'Users.access.saved' : 'Users.access.unchanged'));
    }

    /**
     * The actor and the target; 404 when the user does not exist, and nobody opens their own
     * access or an account they may not manage.
     *
     * @return array{0: User, 1: User}
     */
    private function users(int|string $id): array
    {
        $actor  = auth()->user();
        $target = (new UserAccounts())->find((int) $id);

        if (! $actor instanceof User || $target === null || $target->deleted_at !== null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! (new UserAccounts())->canManage($actor, $target)) {
            security_log('Users', 'access_refused', ['resource' => 'users-users', 'record_id' => $target->id, 'summary' => (string) $target->username]);

            throw PageNotFoundException::forPageNotFound();
        }

        return [$actor, $target];
    }
}
