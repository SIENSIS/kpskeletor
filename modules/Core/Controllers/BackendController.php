<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Crud\AuditedCrud;
use Modules\Core\Crud\CrudFactory;
use Modules\Core\Crud\CrudInspection;
use Modules\Core\Permissions\Authorization;
use Psr\Log\LoggerInterface;

/**
 * Base controller of every back-office screen.
 *
 * Renders through the back-office theme (`Theme.backend`): views extend
 * `theme_layout('backend')` and module views can be overridden by the theme
 * (`Views/overrides/<Module>/…`). kpcrudy CRUDs: `crud()` / `renderCrud()` (docs/cruds.md).
 * Every back-office route still declares its own `can:` filter; `can()` here is for checks
 * inside an action.
 */
abstract class BackendController extends BaseController
{
    /** @var list<array{label: string, url: string|null}> */
    protected array $breadcrumbs = [];

    /**
     * The logged-in user. Back-office routes run the `session` filter, so a request without a
     * user never reaches an action.
     *
     * @throws \RuntimeException when nobody is logged in
     */
    protected function currentUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('No logged-in user.');
        }

        return $user;
    }

    /**
     * The logged-in user, or a stand-in that holds nothing when there is none (only
     * `permissions:check` builds a CRUD that way): record rules written against it deny.
     */
    protected function currentUserOrNobody(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : new User(['id' => 0, 'username' => '']);
    }

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        service('theme')->setArea('backend');
    }

    /**
     * Whether the current user holds one of the permissions (central `Authorization`: default
     * deny for guests, superadmins bypass).
     */
    protected function can(string ...$permissions): bool
    {
        return Authorization::current()->can(...$permissions);
    }

    /**
     * A kpcrudy CRUD for a resource of the catalogue (`CrudFactory::make()`): permissions through
     * `CrudPermissions`, masked ids, the theme's back-office layout.
     */
    protected function crud(string $resource): AuditedCrud
    {
        return CrudFactory::make($resource);
    }

    /**
     * Renders a CRUD inside the back-office layout (page header with the CRUD's title and the
     * breadcrumbs; messages as toasts). Returns kpcrudy's response as is for redirects and
     * downloads, so the action must return it.
     */
    protected function renderCrud(Crud $crud): ResponseInterface|string
    {
        if (CrudFactory::inspecting()) {
            throw new CrudInspection($crud);
        }

        $crud->setLayoutData(['breadcrumbs' => $this->breadcrumbs()]);

        return $crud->render();
    }

    /**
     * Re-authentication ("sudo mode") inside an action: null when the user confirmed their
     * identity recently, otherwise the response to return (the confirmation screen, or 401 for
     * JSON). Same rules as the `reauth` route filter, which is preferred on routes.
     *
     * ```php
     * if (($response = $this->requireReauth()) !== null) {
     *     return $response;
     * }
     * ```
     */
    protected function requireReauth(): ?ResponseInterface
    {
        $class = config('Filters')->aliases['reauth'] ?? null;

        if (! is_string($class) || ! is_subclass_of($class, FilterInterface::class)) {
            // Default deny: without the filter (registered by the Auth module) nothing passes.
            return service('response')->setStatusCode(403);
        }

        $result = (new $class())->before($this->request);

        return $result instanceof ResponseInterface ? $result : null;
    }

    /**
     * Adds a breadcrumb; the layout prints them (the first one is usually the module). Without
     * any, the breadcrumbs come from the generated menu (the trail to the active entry).
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
        $crumbs = $this->breadcrumbs;

        if ($crumbs === []) {
            foreach (backend_menu_trail() as $entry) {
                $crumbs[] = ['label' => $entry->text(), 'url' => $entry->url()];
            }
        }

        return [['label' => lang('Core.ui.backOffice'), 'url' => base_url('backend')], ...$crumbs];
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
     * A message shown once on the next page as a toast (`message`, `success`, `info`, `warning`
     * or `error`).
     */
    protected function flash(string $message, string $type = 'message'): static
    {
        session()->setFlashdata(in_array($type, ['message', 'success', 'info', 'warning', 'error'], true) ? $type : 'message', $message);

        return $this;
    }

    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }
}
