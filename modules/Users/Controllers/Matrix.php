<?php

declare(strict_types=1);

namespace Modules\Users\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\EscalationException;
use Modules\Users\Services\PermissionMatrix;

/**
 * The permission matrix (`backend/users/permissions`): `users-matrix.view` shows it,
 * `users-matrix.edit` + `reauth` save it (atomically, escalation rules first; `PermissionMatrix`).
 * Columns the user may not change are read-only (their own groups, `superadmin`).
 */
final class Matrix extends BackendController
{
    public function index(): string
    {
        $actor  = $this->actor();
        $matrix = new PermissionMatrix();
        $groups = (new GroupRegistry())->all();
        $edit   = $this->can('users-matrix.edit');

        $editable = [];

        foreach (array_keys($groups) as $group) {
            $editable[$group] = $edit && $matrix->canEditGroup($actor, $group);
        }

        return $this->render('Modules\Users\Views\matrix', [
            'title'    => lang('Users.matrix.title'),
            'subtitle' => lang('Users.matrix.subtitle'),
            'icon'     => 'bi bi-grid-3x3',
            'blocks'   => $matrix->blocks(),
            'groups'   => $groups,
            'grants'   => $matrix->grants(),
            'editable' => $editable,
            'canSave'  => in_array(true, $editable, true),
            'formAction' => site_url('backend/users/permissions'),
        ]);
    }

    public function save(): RedirectResponse
    {
        $posted = $this->request->getPost('grants');
        $back   = site_url('backend/users/permissions');

        try {
            $changed = (new PermissionMatrix())->save($this->actor(), is_array($posted) ? $posted : []);
        } catch (EscalationException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        return redirect()->to($back)->with($changed === [] ? 'info' : 'success', $changed === []
            ? lang('Users.matrix.unchanged')
            : lang('Users.matrix.saved', [implode(', ', $changed)]));
    }

    private function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('No logged-in user.');
        }

        return $user;
    }
}
