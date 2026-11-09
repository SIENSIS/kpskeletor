<?php

declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;
use Modules\Core\Widgets\WidgetOptions;
use Modules\Core\Widgets\WidgetSizes;
use Modules\Dashboard\Services\DashboardLayout;

/**
 * Dashboard › Group layouts (`backend/dashboard/groups[/<group>]`, `dashboard.configure`): for
 * each group, which widgets are available, their default order and size and their options. A
 * widget is still shown only to users who hold its permission; the screen says when the group
 * does not hold it. Saving is written to the activity log.
 */
final class GroupLayout extends BackendController
{
    public function index(): string
    {
        $groups = (new GroupRegistry())->all();

        return $this->page((string) array_key_first($groups), $groups);
    }

    public function show(string $group): string
    {
        return $this->page($group, (new GroupRegistry())->all());
    }

    public function save(string $group): RedirectResponse
    {
        if ((new GroupRegistry())->find($group) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $layout  = new DashboardLayout();
        $options = new WidgetOptions();
        $posted  = $this->request->getPost('widgets');
        $posted  = is_array($posted) ? $posted : [];
        $back    = site_url('backend/dashboard/groups/' . $group);
        $result  = [];
        $errors  = [];

        foreach ($layout->registry()->all() as $id => $widget) {
            $row      = is_array($posted[$id] ?? null) ? $posted[$id] : [];
            $position = $row['position'] ?? '';
            $size     = $row['size'] ?? '';

            if (! is_string($position) || preg_match('/^\d{1,4}$/', $position) !== 1) {
                $errors[] = lang('Dashboard.groups.invalidPosition', [$widget->title()]);
            }

            if (! is_string($size) || ! in_array($size, WidgetSizes::allowed($widget), true)) {
                $errors[] = lang('Dashboard.groups.invalidSize', [$widget->title()]);
            }

            $checked = $options->validate($widget, is_array($row['options'] ?? null) ? $row['options'] : []);

            foreach ($checked['errors'] as $error) {
                $errors[] = $widget->title() . ': ' . $error;
            }

            $result[$id] = [
                'enabled'  => ($row['enabled'] ?? '0') === '1',
                'position' => is_string($position) ? (int) $position : 0,
                'size'     => is_string($size) ? $size : '',
                'options'  => $checked['values'],
            ];
        }

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('error', implode(' ', $errors));
        }

        $before = $layout->groupLayout($group);
        $layout->saveGroup($group, $result);

        activity_log('Dashboard', 'group_layout_saved', [
            'resource' => 'dashboard',
            'summary'  => $group,
            'before'   => self::summary($before),
            'after'    => self::summary($layout->groupLayout($group)),
        ]);

        return redirect()->to($back)->with('success', lang('Dashboard.groups.saved'));
    }

    /**
     * @param array<string, array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}> $groups
     */
    private function page(string $group, array $groups): string
    {
        if (! isset($groups[$group])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $layout  = new DashboardLayout();
        $options = new WidgetOptions();
        $grants  = (new PermissionCatalogue())->allGrants()[$group] ?? [];
        $rows    = [];

        foreach ($layout->groupLayout($group) as $id => $config) {
            $widget = $layout->registry()->get($id);

            if ($widget === null) {
                continue;
            }

            $permission = $widget->permission();
            $fields     = [];

            foreach ($widget->settings() as $key => $definition) {
                $fields[$key] = $definition + ['choices' => $options->choices($definition), 'value' => $config['options'][$key] ?? null];
            }

            $rows[] = [
                'id'         => $id,
                'title'      => $widget->title(),
                'icon'       => $widget->icon(),
                'module'     => (string) $layout->registry()->module($id),
                'permission' => $permission,
                'holds'      => $permission === null || $group === 'superadmin' || self::holds($grants, $permission),
                'enabled'    => $config['enabled'],
                'position'   => $config['position'],
                'size'       => $config['size'],
                'sizes'      => WidgetSizes::allowed($widget),
                'fields'     => $fields,
            ];
        }

        $this->breadcrumb(lang('Dashboard.groups.title'), site_url('backend/dashboard/groups'))->breadcrumb($groups[$group]['title']);

        return $this->render('Modules\Dashboard\Views\groups', [
            'title'      => lang('Dashboard.groups.title'),
            'subtitle'   => lang('Dashboard.groups.subtitle'),
            'icon'       => 'bi bi-grid-1x2',
            'groups'     => $groups,
            'group'      => $group,
            'rows'       => $rows,
            'formAction' => site_url('backend/dashboard/groups/' . $group),
        ]);
    }

    /**
     * @param list<string> $grants
     */
    private static function holds(array $grants, string $permission): bool
    {
        foreach ($grants as $grant) {
            if (ShieldMatrix::matching($grant, [$permission]) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the activity log keeps of a layout: "on/off, position, size" per widget.
     *
     * @param array<string, array{enabled: bool, position: int, size: string, options: array<string, mixed>}> $layout
     *
     * @return array<string, string>
     */
    private static function summary(array $layout): array
    {
        $summary = [];

        foreach ($layout as $id => $config) {
            $summary[$id] = ($config['enabled'] ? 'on' : 'off') . ', ' . $config['position'] . ', ' . $config['size']
                . ($config['options'] === [] ? '' : ', ' . json_encode($config['options']));
        }

        return $summary;
    }
}
