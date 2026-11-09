<?php

declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Widgets\AsyncWidgetInterface;
use Modules\Core\Widgets\WidgetRenderer;
use Modules\Core\Widgets\WidgetSizes;
use Modules\Dashboard\Services\DashboardLayout;

/**
 * The back-office home (`backend`, `dashboard.view`): the user's widgets in their order and
 * sizes. With JavaScript (`dashboard/dashboard`) "Customise" turns on drag and drop (mouse,
 * touch and keyboard), resizing and hiding, saved with `POST backend/dashboard/layout`; without
 * it, the same link opens a form (`backend/dashboard/customize`). "Restore the group layout"
 * forgets the user's own layout.
 */
final class Dashboard extends BackendController
{
    public function index(): string
    {
        $user     = $this->user();
        $layout   = new DashboardLayout();
        $renderer = new WidgetRenderer();
        $cards    = [];
        $hidden   = [];

        foreach ($layout->forUser($user) as $entry) {
            $widget = $entry['widget'];

            if ($entry['hidden']) {
                $hidden[] = ['id' => $entry['id'], 'title' => $widget->title()];

                continue;
            }

            $sizes   = WidgetSizes::allowed($widget);
            $cards[] = $renderer->card($widget, $entry['options'], $entry['size'], $this->dataUrl($entry['id'], $widget), [
                'data-sizes' => implode(',', $sizes),
            ]);
        }

        $actions = [];

        if ($this->can('dashboard.configure')) {
            $actions[] = ['label' => lang('Dashboard.groups.title'), 'url' => site_url('backend/dashboard/groups'), 'icon' => 'bi bi-grid-1x2'];
        }

        return $this->render('Modules\Dashboard\Views\dashboard', [
            'title'      => lang('Dashboard.title'),
            'icon'       => 'bi bi-speedometer2',
            'actions'    => $actions,
            'cards'      => $cards,
            'hidden'     => $hidden,
            'ownLayout'  => $layout->hasUserLayout($user),
            'sizes'      => array_combine(WidgetSizes::ALL, array_map(WidgetSizes::label(...), WidgetSizes::ALL)),
            'saveUrl'    => site_url('backend/dashboard/layout'),
            'resetUrl'   => site_url('backend/dashboard/layout/reset'),
        ]);
    }

    /**
     * The layout form without JavaScript: position, size and "show" of every available widget.
     */
    public function customize(): string
    {
        $user = $this->user();
        $rows = [];

        foreach ((new DashboardLayout())->forUser($user) as $i => $entry) {
            $rows[] = [
                'id'       => $entry['id'],
                'title'    => $entry['widget']->title(),
                'position' => ($i + 1) * 10,
                'size'     => $entry['size'],
                'sizes'    => WidgetSizes::allowed($entry['widget']),
                'hidden'   => $entry['hidden'],
            ];
        }

        $this->breadcrumb(lang('Dashboard.title'), site_url('backend'))->breadcrumb(lang('Dashboard.customize'));

        return $this->render('Modules\Dashboard\Views\customize', [
            'title'    => lang('Dashboard.customize'),
            'subtitle' => lang('Dashboard.customizeHelp'),
            'icon'     => 'bi bi-sliders',
            'rows'     => $rows,
            'saveUrl'  => site_url('backend/dashboard/layout'),
            'resetUrl' => site_url('backend/dashboard/layout/reset'),
        ]);
    }

    /**
     * Saves the user's layout: JSON `{"widgets": [{"id", "size", "hidden"}, …]}` from the
     * dashboard script, or the customise form (`widgets[<id>][position|size|visible]`).
     */
    public function save(): ResponseInterface
    {
        $json = $this->wantsJson() && str_contains($this->request->getHeaderLine('Content-Type'), 'json');

        if ($json) {
            $body    = json_decode((string) $this->request->getBody(), true);
            $entries = is_array($body) && is_array($body['widgets'] ?? null) ? array_values($body['widgets']) : null;
        } else {
            $entries = self::fromForm($this->request->getPost('widgets'));
        }

        if ($entries === null) {
            return $this->answer(lang('Dashboard.invalid'), 'error', 422);
        }

        (new DashboardLayout())->saveUser($this->user(), $entries);

        return $this->answer(lang('Dashboard.saved'), 'success');
    }

    /**
     * "Restore the group layout".
     */
    public function reset(): ResponseInterface
    {
        (new DashboardLayout())->resetUser($this->user());

        return $this->answer(lang('Dashboard.restored'), 'success');
    }

    /**
     * The form's rows sorted by position.
     *
     * @return list<array{id: string, size: string|null, hidden: bool}>|null
     */
    private static function fromForm(mixed $posted): ?array
    {
        if (! is_array($posted)) {
            return null;
        }

        $rows = [];

        foreach ($posted as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                return null;
            }

            $position = $row['position'] ?? '';
            $rows[]   = [
                'id'       => $id,
                'size'     => is_string($row['size'] ?? null) ? $row['size'] : null,
                'hidden'   => ($row['visible'] ?? '0') !== '1',
                'position' => is_string($position) && preg_match('/^-?\d{1,6}$/', $position) === 1 ? (int) $position : PHP_INT_MAX,
            ];
        }

        $order = array_flip(array_column($rows, 'id'));
        usort($rows, static fn (array $a, array $b): int => [$a['position'], $order[$a['id']]] <=> [$b['position'], $order[$b['id']]]);

        return array_map(static fn (array $row): array => ['id' => $row['id'], 'size' => $row['size'], 'hidden' => $row['hidden']], $rows);
    }

    private function dataUrl(string $id, object $widget): ?string
    {
        return $widget instanceof AsyncWidgetInterface ? site_url('backend/dashboard/widgets/' . $id . '/data') : null;
    }

    private function wantsJson(): bool
    {
        return $this->request->isAJAX() || str_contains($this->request->getHeaderLine('Accept'), 'application/json');
    }

    private function answer(string $message, string $type, int $status = 200): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->json($status < 400 ? ['message' => $message] : ['error' => $message], $status);
        }

        return redirect()->to(site_url('backend'))->with($type, $message);
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new \RuntimeException('No logged-in user.');
        }

        return $user;
    }
}
