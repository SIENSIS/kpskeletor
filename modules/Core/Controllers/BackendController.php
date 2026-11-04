<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base controller of every back-office screen.
 *
 * Renders through the back-office theme (`Theme.backend`): views extend
 * `theme_layout('backend')` and module views can be overridden by the theme
 * (`Views/overrides/<Module>/…`). The CRUD helpers (`crud()` / `renderCrud()`) are added with
 * kpcrudy and toasts with the front-end session. Every back-office route still declares its own
 * `can:` filter; `can()` here is for checks inside an action.
 */
abstract class BackendController extends BaseController
{
    /** @var list<array{label: string, url: string|null}> */
    protected array $breadcrumbs = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        service('theme')->setArea('backend');
    }

    /**
     * Whether the current user has a permission (default deny for guests).
     */
    protected function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }

    /**
     * Adds a breadcrumb; the layout prints them (the first one is usually the module).
     */
    protected function breadcrumb(string $label, ?string $url = null): static
    {
        $this->breadcrumbs[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    protected function breadcrumbs(): array
    {
        return [['label' => lang('Core.ui.backOffice'), 'url' => base_url('backend')], ...$this->breadcrumbs];
    }

    /**
     * The layout view of the back office, for `$this->extend()` and kpcrudy's `setLayout()`.
     */
    protected function layout(): string
    {
        return theme_layout('backend');
    }

    /**
     * Renders a view through the theme. Adds `breadcrumbs`, `layout` and, when the data has a
     * `title`, the `pageHeader` the layout prints (title, `subtitle`, `icon`, breadcrumbs and
     * `actions`), unless they are given.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        $data['breadcrumbs'] ??= $this->breadcrumbs();
        $data['layout'] ??= $this->layout();

        if (! array_key_exists('pageHeader', $data) && is_string($data['title'] ?? null)) {
            $data['pageHeader'] = [
                'title'       => $data['title'],
                'subtitle'    => $data['subtitle'] ?? null,
                'icon'        => $data['icon'] ?? null,
                'breadcrumbs' => $data['breadcrumbs'],
                'actions'     => $data['actions'] ?? [],
            ];
        }

        return theme_view($view, $data);
    }

    /**
     * A message shown once on the next page (`message`, `success`, `warning` or `error`).
     */
    protected function flash(string $message, string $type = 'message'): static
    {
        session()->setFlashdata(in_array($type, ['message', 'success', 'warning', 'error'], true) ? $type : 'message', $message);

        return $this;
    }

    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }
}
