<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base controller of every back-office screen.
 *
 * Skeleton: the layout, breadcrumbs, CRUD helpers (`crud()` / `renderCrud()`) and toasts are added
 * by later sessions (themes, RBAC, kpcrudy). Every back-office route still declares its own
 * `can:` filter; `can()` here is for checks inside an action.
 */
abstract class BackendController extends BaseController
{
    /**
     * Whether the current user has a permission (default deny for guests).
     */
    protected function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data);
    }

    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }
}
